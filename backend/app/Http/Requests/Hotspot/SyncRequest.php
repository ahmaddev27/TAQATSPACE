<?php

declare(strict_types=1);

namespace App\Http\Requests\Hotspot;

use App\Services\Hotspot\SyncPayload;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a router sync batch. Authorization is the `router` middleware's
 * job (key + workspace code); the limits here keep a batch small enough for
 * a single transaction.
 */
class SyncRequest extends FormRequest
{
    public const MAX_EVENTS = 500;

    public const MAX_SNAPSHOT_CHUNKS = 200;

    public const MAX_CHUNK_BYTES = 60000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'string', 'max:64'],
            'boot_id' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_.]+$/'],
            'seq' => ['required', 'integer', 'min:0'],
            'now' => ['required', 'integer', 'min:0'],
            'serial' => ['nullable', 'string', 'max:100'],
            'routeros_version' => ['nullable', 'string', 'max:50'],
            'script_version' => ['nullable', 'string', 'max:50'],
            'events' => ['nullable', 'array', 'max:'.self::MAX_EVENTS],
            'events.*.seq' => ['required', 'integer', 'min:0'],
            'events.*.type' => ['required', 'string', 'max:30', 'alpha_dash'],
            'events.*.at' => ['required', 'integer', 'min:0'],
            'events.*.mac' => ['nullable', 'string', 'max:17'],
            'events.*.ip' => ['nullable', 'ip'],
            'events.*.username' => ['nullable', 'string', 'max:100'],
            'events.*.data' => ['nullable', 'array'],
            'snapshot' => ['nullable', 'array'],
            'snapshot.id' => ['required_with:snapshot', 'string', 'max:64'],
            'snapshot.index' => ['required_with:snapshot', 'integer', 'min:0'],
            'snapshot.total' => ['required_with:snapshot', 'integer', 'min:1', 'max:'.self::MAX_SNAPSHOT_CHUNKS],
            'snapshot.taken_at' => ['required_with:snapshot', 'integer', 'min:0'],
            'snapshot.data' => ['required_with:snapshot', 'string', 'max:'.self::MAX_CHUNK_BYTES],
            // Command acknowledgements; consumed by the command queue (2.5).
            'acks' => ['nullable', 'array', 'max:100'],
            'acks.*' => ['string', 'max:64'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $snapshot = $this->input('snapshot');

                if (is_array($snapshot) && ($snapshot['index'] ?? 0) >= ($snapshot['total'] ?? 0)) {
                    $validator->errors()->add('snapshot.index', 'The snapshot index must be lower than its total.');
                }
            },
        ];
    }

    public function payload(): SyncPayload
    {
        return SyncPayload::fromValidated($this->validated());
    }
}
