<?php

declare(strict_types=1);

namespace App\Testing\Hotspot;

/**
 * PHP twin of resources/routeros/interpreter.rsc: the same verb allowlist, the
 * same poll-only restriction and the same argument patterns, applied to a
 * virtual router instead of RouterOS. It never executes the reply text; each
 * verb only updates the in-memory state below.
 */
final class RouterInterpreter
{
    private const ALLOWED = ['upsert-user', 'disable-user', 'remove-active', 'block-mac', 'set-key', 'update-script', 'upsert-profile'];

    private const POLL_ONLY = ['set-key', 'update-script'];

    private const NAME = '/^[A-Za-z0-9._@-]{1,64}$/D';

    private const MAC = '/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/D';

    private const RATE_LIMIT = '/^[0-9A-Za-z\/]{0,40}$/D';

    private const KEY = '/^[0-9a-f]{32,96}$/D';

    /** @var array<string, array{password: string, profile: string, disabled: bool}> */
    public array $users = [];

    /** @var array<string, string> profile name => rate limit */
    public array $profiles = [];

    /** @var array<string, true> */
    public array $blockedMacs = [];

    /** Set by `set-key`; the simulator adopts it for the next request. */
    public ?string $newKey = null;

    public function __construct(private readonly string $mode = 'full') {}

    /**
     * @param  list<string>  $fields  the split `C|id|verb|arg1|arg2|arg3` line
     * @return string `ok` or the rejection reason the router would acknowledge
     */
    public function run(array $fields): string
    {
        $verb = $fields[2] ?? '';
        [$a1, $a2, $a3] = [$fields[3] ?? '', $fields[4] ?? '', $fields[5] ?? ''];

        if (! in_array($verb, self::ALLOWED, true)) {
            return 'not-allowed';
        }

        if ($this->mode === 'poll-only' && ! in_array($verb, self::POLL_ONLY, true)) {
            return 'poll-only';
        }

        return match ($verb) {
            'upsert-user' => $this->upsertUser($a1, $a2, $a3),
            'disable-user' => $this->disableUser($a1),
            'remove-active' => $this->matches(self::NAME, $a1) ? 'ok' : 'bad-args',
            'block-mac' => $this->blockMac($a1),
            'upsert-profile' => $this->upsertProfile($a1, $a2),
            'set-key' => $this->setKey($a1),
            // The simulator has no script store to fetch from, so it reports the router's own failure reason.
            default => $this->matches(self::NAME, $a1) ? 'failed' : 'bad-args',
        };
    }

    private function upsertUser(string $name, string $password, string $profile): string
    {
        $profile = $profile === '' ? 'visitor' : $profile;

        if (! $this->matches(self::NAME, $name) || $password === '' || strlen($password) > 64 || ! $this->matches(self::NAME, $profile)) {
            return 'bad-args';
        }

        $this->users[$name] = ['password' => $password, 'profile' => $profile, 'disabled' => false];

        return 'ok';
    }

    private function disableUser(string $name): string
    {
        if (! $this->matches(self::NAME, $name)) {
            return 'bad-args';
        }

        if (isset($this->users[$name])) {
            $this->users[$name]['disabled'] = true;
        }

        return 'ok';
    }

    private function blockMac(string $mac): string
    {
        if (! $this->matches(self::MAC, $mac)) {
            return 'bad-args';
        }

        $this->blockedMacs[$mac] = true;

        return 'ok';
    }

    private function upsertProfile(string $name, string $rateLimit): string
    {
        if (! $this->matches(self::NAME, $name) || ! $this->matches(self::RATE_LIMIT, $rateLimit)) {
            return 'bad-args';
        }

        $this->profiles[$name] = $rateLimit;

        return 'ok';
    }

    private function setKey(string $key): string
    {
        if (! $this->matches(self::KEY, $key)) {
            return 'bad-args';
        }

        $this->newKey = $key;

        return 'ok';
    }

    private function matches(string $pattern, string $value): bool
    {
        return preg_match($pattern, $value) === 1;
    }
}
