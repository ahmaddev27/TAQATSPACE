<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\TwoFactorThrottledException;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;

/**
 * TOTP two-factor authentication: enrollment, code / recovery-code
 * verification with throttling, and the "recently verified" marker used by the
 * step-up middleware.
 */
class TwoFactorService
{
    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_SECONDS = 300;

    public const RECOVERY_CODE_COUNT = 10;

    private const MARKER_PREFIX = '2fa:verified:';

    private const MARKER_TTL_SECONDS = 43200; // 12 hours; freshness is judged by the stored timestamp

    public function __construct(
        private readonly Google2FA $google2fa,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Start (or restart) enrollment: store a fresh encrypted secret that stays
     * unconfirmed until a valid code is presented.
     *
     * @return array{secret: string, otpauth_url: string, qr_svg: string}
     */
    public function enable(User $user): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw new RuntimeException('Two-factor authentication is already enabled.');
        }

        $secret = $this->google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $url = $this->google2fa->getQRCodeUrl((string) config('app.name'), (string) $user->email, $secret);

        return ['secret' => $secret, 'otpauth_url' => $url, 'qr_svg' => $this->qrSvg($url)];
    }

    /**
     * Confirm enrollment with a code from the authenticator app.
     *
     * @return list<string>|null Plain recovery codes (shown once), null on a wrong code.
     */
    public function confirm(User $user, string $code): ?array
    {
        if ($user->two_factor_secret === null || $user->hasTwoFactorEnabled()) {
            throw new RuntimeException('There is no pending two-factor enrollment.');
        }

        if (! $this->verifyTotp($user, $code)) {
            return null;
        }

        $plain = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(Hash::make(...), $plain),
        ])->save();

        $this->markVerified($user);
        $this->audit->log('auth.two_factor.enabled', $user, actor: $user);

        return $plain;
    }

    /** Verify a TOTP code (throttled). Marks the session as freshly verified on success. */
    public function verify(User $user, string $code): bool
    {
        if (! $user->hasTwoFactorEnabled() || ! $this->verifyTotp($user, $code)) {
            return false;
        }

        $this->markVerified($user);

        return true;
    }

    /** Consume a single-use recovery code (throttled). */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $this->guardThrottle($user);

        $hashes = $user->two_factor_recovery_codes ?? [];
        $needle = Str::lower(trim($code));

        foreach ($hashes as $index => $hash) {
            if (Hash::check($needle, $hash)) {
                unset($hashes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();
                $this->clearFailures($user);
                $this->markVerified($user);
                $this->audit->log('auth.two_factor.recovery_code_used', $user, changes: ['remaining' => count($hashes)], actor: $user);

                return true;
            }
        }

        $this->recordFailure($user);

        return false;
    }

    /** Verify either a TOTP code or a recovery code, whichever the caller supplied. */
    public function verifyAny(User $user, ?string $code, ?string $recoveryCode): bool
    {
        if ($recoveryCode !== null && $recoveryCode !== '') {
            return $this->useRecoveryCode($user, $recoveryCode);
        }

        return $this->verify($user, (string) $code);
    }

    /** Turn 2FA off; the caller has already verified a code. */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $this->forgetVerified($user);
        $this->audit->log('auth.two_factor.disabled', $user, actor: $user);
    }

    public function markVerified(User $user): void
    {
        Cache::put($this->markerKey($user), now()->getTimestamp(), self::MARKER_TTL_SECONDS);
    }

    /** Record a verification for a specific access token (e.g. when the IdP asserted MFA). */
    public function markTokenVerified(int|string $accessTokenId): void
    {
        Cache::put(self::MARKER_PREFIX.'t'.$accessTokenId, now()->getTimestamp(), self::MARKER_TTL_SECONDS);
    }

    public function forgetVerified(User $user): void
    {
        Cache::forget($this->markerKey($user));
    }

    /** Whether the current session passed a 2FA check within the last $minutes. */
    public function isRecentlyVerified(User $user, int $minutes): bool
    {
        $verifiedAt = Cache::get($this->markerKey($user));

        return is_int($verifiedAt) && now()->getTimestamp() - $verifiedAt <= $minutes * 60;
    }

    private function verifyTotp(User $user, string $code): bool
    {
        $this->guardThrottle($user);

        $valid = $user->two_factor_secret !== null
            && $this->google2fa->verifyKey($user->two_factor_secret, trim($code));

        $valid ? $this->clearFailures($user) : $this->recordFailure($user);

        return $valid;
    }

    private function guardThrottle(User $user): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey($user), self::MAX_ATTEMPTS)) {
            throw new TwoFactorThrottledException(RateLimiter::availableIn($this->throttleKey($user)));
        }
    }

    private function recordFailure(User $user): void
    {
        RateLimiter::hit($this->throttleKey($user), self::LOCKOUT_SECONDS);
    }

    private function clearFailures(User $user): void
    {
        RateLimiter::clear($this->throttleKey($user));
    }

    private function throttleKey(User $user): string
    {
        return '2fa:'.$user->getKey();
    }

    /**
     * Keyed per access token so one device's verification never satisfies the
     * step-up check of another session.
     */
    private function markerKey(User $user): string
    {
        $token = $user->currentAccessToken();
        $scope = $token instanceof PersonalAccessToken ? 't'.$token->getKey() : 'u'.$user->getKey();

        return self::MARKER_PREFIX.$scope;
    }

    /** @return list<string> */
    private function generateRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn (): string => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    private function qrSvg(string $url): string
    {
        $renderer = new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($url);
    }
}
