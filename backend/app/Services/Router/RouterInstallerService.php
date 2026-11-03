<?php

declare(strict_types=1);

namespace App\Services\Router;

use App\Exceptions\InstallTokenException;
use App\Models\Router;
use App\Models\RouterInstallToken;
use App\Services\Audit\AuditLogger;
use App\Support\Router\RouterKeyHasher;
use App\Support\Router\RouterOsString;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues one-time install tokens and renders the per-router install.rsc.
 * The router key is rotated at issue time (the old key keeps working for the
 * grace period) and travels inside the script exactly once.
 */
class RouterInstallerService
{
    public const TOKEN_TTL_MINUTES = 30;

    /** Scripts the installer pulls after bootstrapping (shipped by task 2.3). */
    public const SCRIPTS = ['install-precheck', 'hooks', 'sync', 'snapshot', 'interpreter'];

    private const VIEW = 'views/routeros/install.rsc.blade.php';

    public function __construct(
        private readonly RouterService $routers,
        private readonly AuditLogger $audit,
    ) {}

    public function issueToken(Router $router): IssuedInstallToken
    {
        $plainToken = Str::random(48);

        $token = DB::transaction(function () use ($router, $plainToken): RouterInstallToken {
            $this->invalidateOpenTokens($router);
            $withKey = $this->routers->rotateKeyHash($router);

            return RouterInstallToken::query()->create([
                'router_id' => $router->getKey(),
                'token_hash' => RouterKeyHasher::hash($plainToken),
                'encrypted_key' => Crypt::encryptString($withKey->plainKey),
                'expires_at' => now()->addMinutes(self::TOKEN_TTL_MINUTES),
            ]);
        });

        $this->audit->log('network.router.install_token_issued', $router);

        $url = $this->installUrl($plainToken);

        return new IssuedInstallToken(
            $url,
            sprintf('/tool fetch url="%s" check-certificate=yes dst-path=install.rsc; /import install.rsc', $url),
            $token->expires_at,
        );
    }

    /**
     * Redeem the token and return the script. Consumption is a single atomic
     * UPDATE so two concurrent fetches can never both succeed.
     *
     * @throws InstallTokenException
     */
    public function render(string $plainToken): string
    {
        $token = RouterInstallToken::query()
            ->where('token_hash', RouterKeyHasher::hash($plainToken))
            ->first() ?? throw InstallTokenException::unknown();

        $consumed = RouterInstallToken::query()
            ->whereKey($token->getKey())
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now(), 'encrypted_key' => null]);

        if ($consumed === 0 || $token->encrypted_key === null) {
            throw InstallTokenException::gone();
        }

        $router = $token->router()->with('workspace')->firstOrFail();
        $this->audit->log('network.router.installer_fetched', $router, workspaceId: $router->workspace_id);

        return $this->renderScript($router, Crypt::decryptString($token->encrypted_key));
    }

    private function renderScript(Router $router, string $plainKey): string
    {
        $escape = static fn (string $value): string => RouterOsString::escape($value);

        return view()->file(resource_path(self::VIEW), [
            'syncUrl' => $escape($this->syncBaseUrl()),
            'workspaceCode' => $escape((string) $router->workspace->code),
            'routerName' => $escape($router->name),
            'key' => $escape($plainKey),
            'scriptList' => '{"'.implode('";"', self::SCRIPTS).'"}',
        ])->render();
    }

    /** Spent tokens are closed, not deleted, so a stale link answers 410 rather than 404. */
    private function invalidateOpenTokens(Router $router): void
    {
        RouterInstallToken::query()
            ->where('router_id', $router->getKey())
            ->whereNull('used_at')
            ->update(['used_at' => now(), 'encrypted_key' => null]);
    }

    private function installUrl(string $plainToken): string
    {
        return $this->syncBaseUrl().'/api/v1/hotspot/install/'.$plainToken.'.rsc';
    }

    private function syncBaseUrl(): string
    {
        return rtrim((string) config('services.router.sync_url'), '/');
    }
}
