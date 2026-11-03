<?php

declare(strict_types=1);

namespace App\Services\Router;

use App\Enums\RouterCommandStatus;
use App\Enums\RouterCommandVerb;
use App\Models\Router;
use App\Models\RouterCommand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The platform-to-router command queue. A command is sent in the next sync
 * reply (requested to delivered), confirmed by an `A|id|status` line in a later
 * batch (acknowledged or failed), resent when no confirmation came within
 * RESEND_AFTER_SYNCS syncs, and expired once its ttl has passed. Every verb is
 * idempotent on the router, so a resend or a duplicate acknowledgement is safe.
 */
class RouterCommandService
{
    public const DEFAULT_TTL_MINUTES = 60;

    public const MAX_COMMANDS_PER_SYNC = 50;

    public const RESEND_AFTER_SYNCS = 3;

    /** After this many sends without an acknowledgement the command is given up on. */
    public const MAX_ATTEMPTS = 5;

    public const ACK_OK = 'ok';

    public const ERROR_UNACKNOWLEDGED = 'unacknowledged';

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException when the verb is not allowlisted or the payload breaks its rules
     */
    public function enqueue(Router $router, RouterCommandVerb|string $verb, array $payload, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): RouterCommand
    {
        $verb = $this->resolveVerb($verb);
        $validated = Validator::make($payload, $verb->rules())->validate();

        return RouterCommand::query()->create([
            'workspace_id' => $router->workspace_id,
            'router_id' => $router->getKey(),
            'verb' => $verb,
            'payload' => $validated,
            'status' => RouterCommandStatus::Requested->value,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
    }

    /**
     * Applies the acknowledgements of a batch, ages the unacknowledged
     * commands and picks the `C|` lines for the reply. Meant to run inside the
     * sync transaction.
     *
     * @param  list<array{command_id: string, status: string}>  $acks
     * @return list<string> wire lines that fit into $maxBytes, oldest command first
     */
    public function syncCycle(Router $router, array $acks, int $maxBytes, bool $pollOnly): array
    {
        $this->acknowledge($router, $acks);
        $this->ageDelivered($router);

        return $this->nextBatchFor($router, $maxBytes, $pollOnly);
    }

    /**
     * @param  list<array{command_id: string, status: string}>  $acks
     */
    public function acknowledge(Router $router, array $acks): void
    {
        $statusById = [];

        foreach ($acks as $ack) {
            if (Str::isUuid($ack['command_id'])) {
                $statusById[$ack['command_id']] = $ack['status'];
            }
        }

        if ($statusById === []) {
            return;
        }

        $confirmed = array_keys(array_filter($statusById, fn (string $status): bool => $status === self::ACK_OK));
        $this->openDelivered($router)->whereIn('id', $confirmed)->update([
            'status' => RouterCommandStatus::Acknowledged->value,
            'acknowledged_at' => now(),
            'error' => null,
        ]);

        $this->failRejected($router, $statusById);
    }

    /**
     * Claims the oldest sendable commands that fit the reply and marks them delivered.
     *
     * @return list<string>
     */
    public function nextBatchFor(Router $router, int $maxBytes, bool $pollOnly = false): array
    {
        $lines = [];
        $ids = [];

        foreach ($this->sendable($router, $pollOnly)->get() as $command) {
            $line = $this->wireLine($command);

            if (strlen($line) + 1 > $maxBytes) {
                break;
            }

            $maxBytes -= strlen($line) + 1;
            $lines[] = $line;
            $ids[] = $command->getKey();
        }

        if ($ids !== []) {
            RouterCommand::query()->withoutWorkspaceScope()->whereIn('id', $ids)->increment('attempts', 1, [
                'status' => RouterCommandStatus::Delivered->value,
                'sync_cycles' => 0,
                'delivered_at' => now(),
            ]);
        }

        return $lines;
    }

    /** Marks every open command past its ttl as expired; run by the scheduler. */
    public function expireDue(): int
    {
        return RouterCommand::query()->withoutWorkspaceScope()
            ->whereIn('status', RouterCommandStatus::openValues())
            ->where('expires_at', '<=', now())
            ->update(['status' => RouterCommandStatus::Expired->value]);
    }

    public function wireLine(RouterCommand $command): string
    {
        return implode('|', ['C', $command->getKey(), $command->verb->value, ...$command->verb->arguments($command->payload)]);
    }

    private function resolveVerb(RouterCommandVerb|string $verb): RouterCommandVerb
    {
        if ($verb instanceof RouterCommandVerb) {
            return $verb;
        }

        return RouterCommandVerb::tryFrom($verb)
            ?? throw ValidationException::withMessages(['verb' => ["The verb [{$verb}] is not allowed."]]);
    }

    /** @return Builder<RouterCommand> */
    private function forRouter(Router $router): Builder
    {
        return RouterCommand::query()->withoutWorkspaceScope()->where('router_id', $router->getKey());
    }

    /** @return Builder<RouterCommand> */
    private function openDelivered(Router $router): Builder
    {
        return $this->forRouter($router)
            ->where('status', RouterCommandStatus::Delivered->value)
            ->where('expires_at', '>', now());
    }

    /**
     * A rejection is final: the router replied but would not run the command.
     *
     * @param  array<string, string>  $statusById
     */
    private function failRejected(Router $router, array $statusById): void
    {
        $idsByReason = [];

        foreach ($statusById as $id => $status) {
            if ($status !== self::ACK_OK) {
                $idsByReason[Str::limit($status, 64, '')][] = $id;
            }
        }

        foreach ($idsByReason as $reason => $ids) {
            $this->openDelivered($router)->whereIn('id', $ids)->update([
                'status' => RouterCommandStatus::Failed->value,
                'error' => (string) $reason,
            ]);
        }
    }

    private function ageDelivered(Router $router): void
    {
        $this->openDelivered($router)->increment('sync_cycles');

        $this->openDelivered($router)
            ->where('sync_cycles', '>=', self::RESEND_AFTER_SYNCS)
            ->where('attempts', '>=', self::MAX_ATTEMPTS)
            ->update(['status' => RouterCommandStatus::Failed->value, 'error' => self::ERROR_UNACKNOWLEDGED]);
    }

    /** @return Builder<RouterCommand> */
    private function sendable(Router $router, bool $pollOnly): Builder
    {
        $query = $this->forRouter($router)
            ->where('expires_at', '>', now())
            ->where(fn (Builder $open) => $open
                ->where('status', RouterCommandStatus::Requested->value)
                ->orWhere(fn (Builder $stale) => $stale
                    ->where('status', RouterCommandStatus::Delivered->value)
                    ->where('sync_cycles', '>=', self::RESEND_AFTER_SYNCS)))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::MAX_COMMANDS_PER_SYNC);

        if ($pollOnly) {
            $query->whereIn('verb', array_map(
                static fn (RouterCommandVerb $verb): string => $verb->value,
                array_filter(RouterCommandVerb::cases(), static fn (RouterCommandVerb $verb): bool => $verb->allowedInPollOnly()),
            ));
        }

        return $query;
    }
}
