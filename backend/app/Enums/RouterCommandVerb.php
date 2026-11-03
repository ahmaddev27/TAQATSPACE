<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * The single source of the verbs a router may be asked to run. It mirrors the
 * allowlist in resources/routeros/interpreter.rsc (RouterProtocolContractTest
 * keeps the two equal) and owns the payload rules, which are as strict as the
 * interpreter's own argument checks: a value that breaks them never leaves
 * the server.
 */
enum RouterCommandVerb: string
{
    use HasValues;

    case UpsertUser = 'upsert-user';
    case DisableUser = 'disable-user';
    case RemoveActive = 'remove-active';
    case BlockMac = 'block-mac';
    case SetKey = 'set-key';
    case UpdateScript = 'update-script';
    case UpsertProfile = 'upsert-profile';

    /** System script names the router accepts for `update-script`. */
    public const SCRIPT_NAMES = ['cws-hooks', 'cws-sync', 'cws-snapshot', 'cws-interpreter'];

    private const NAME = 'regex:/^[A-Za-z0-9._@-]{1,64}$/D';

    /** Printable ASCII without space and without the `|` wire separator. */
    private const PASSWORD = 'regex:/^[\x21-\x7B\x7D-\x7E]{1,64}$/D';

    /**
     * Verbs that never touch hotspot users, profiles or bindings; a router in
     * poll-only mode still runs these.
     */
    public function allowedInPollOnly(): bool
    {
        return in_array($this, [self::SetKey, self::UpdateScript], true);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return match ($this) {
            self::UpsertUser => ['username' => ['required', 'string', self::NAME], 'password' => ['required', 'string', self::PASSWORD], 'profile' => ['nullable', 'string', self::NAME]],
            self::DisableUser, self::RemoveActive => ['username' => ['required', 'string', self::NAME]],
            self::BlockMac => ['mac' => ['required', 'string', 'regex:/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/D']],
            self::UpsertProfile => ['name' => ['required', 'string', self::NAME], 'rate_limit' => ['present', 'nullable', 'string', 'regex:/^[0-9A-Za-z\/]{0,40}$/D']],
            self::SetKey => ['key' => ['required', 'string', 'regex:/^[0-9a-f]{32,96}$/D']],
            self::UpdateScript => ['script' => ['required', 'string', 'in:'.implode(',', self::SCRIPT_NAMES)], 'sha256' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/D']],
        };
    }

    /**
     * Positional arguments of the `C|id|verb|arg1|arg2|arg3` line, in the order
     * interpreter.rsc reads them.
     *
     * @param  array<string, mixed>  $payload  validated against rules()
     * @return list<string>
     */
    public function arguments(array $payload): array
    {
        return array_map(
            static fn (string $field): string => (string) ($payload[$field] ?? ''),
            array_keys($this->rules()),
        );
    }
}
