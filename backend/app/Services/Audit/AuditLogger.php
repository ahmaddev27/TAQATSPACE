<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\AuditActorType;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes entries to the append-only audit log. Services call it explicitly for
 * every sensitive or financial action; the IP and request id come from the
 * current request automatically, and the actor defaults to the authenticated
 * user (or `system` outside a user context, e.g. queue/CLI).
 */
class AuditLogger
{
    /**
     * @param  string  $action  Dotted name, e.g. `finance.invoice.issued`.
     * @param  array<string, mixed>|null  $changes
     * @param  string|null  $workspaceId  Defaults to the subject's / actor's workspace.
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?string $reason = null,
        ?array $changes = null,
        ?User $actor = null,
        ?string $workspaceId = null,
        AuditActorType $actorType = AuditActorType::User,
    ): AuditLog {
        $request = request();
        $actor ??= $actorType === AuditActorType::User ? $request->user() : null;
        $type = $actor === null && $actorType === AuditActorType::User ? AuditActorType::System : $actorType;

        return AuditLog::query()->create([
            'workspace_id' => $workspaceId ?? $this->workspaceOf($subject, $actor),
            'actor_type' => $type->value,
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'reason' => $reason,
            'changes' => $changes,
            'ip' => $request->ip(),
            'request_id' => $request->attributes->get('request_id') ?? (string) Str::uuid(),
        ]);
    }

    private function workspaceOf(?Model $subject, ?User $actor): ?string
    {
        $id = $subject instanceof Workspace
            ? $subject->getKey()
            : ($subject?->getAttribute('workspace_id') ?? $actor?->workspace_id);

        return $id === null ? null : (string) $id;
    }
}
