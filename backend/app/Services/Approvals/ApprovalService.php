<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\AdminPermission;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ApprovalDecidedNotification;
use App\Notifications\ApprovalRequestedNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

/**
 * Lifecycle of an approval request: pending → approved | rejected | cancelled.
 * Submitting never touches the target data; only an approval calls the type's
 * handler, exactly once, in the same transaction as the status change.
 */
class ApprovalService
{
    public function __construct(
        private readonly ApprovalHandlerRegistry $handlers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function submit(Workspace $workspace, ApprovalType $type, array $payload, User $requester): ApprovalRequest
    {
        $validated = $this->handlers->for($type)->validate($payload);

        $request = DB::transaction(function () use ($workspace, $type, $validated, $requester): ApprovalRequest {
            $request = ApprovalRequest::query()->create([
                'workspace_id' => $workspace->id,
                'type' => $type,
                'payload' => $validated,
                'status' => ApprovalStatus::Pending,
                'requested_by' => $requester->id,
            ]);

            $this->audit->log('approval.requested', $request, null, ['type' => $type->value], $requester, $workspace->id);

            return $request;
        });

        Notification::send($this->approvers(), new ApprovalRequestedNotification($request, $workspace));

        return $request;
    }

    public function approve(ApprovalRequest $request, User $admin): ApprovalRequest
    {
        return $this->decide($request, $admin, ApprovalStatus::Approved, null);
    }

    public function reject(ApprovalRequest $request, User $admin, string $reason): ApprovalRequest
    {
        return $this->decide($request, $admin, ApprovalStatus::Rejected, $reason);
    }

    public function cancel(ApprovalRequest $request, User $requester): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $requester): ApprovalRequest {
            $locked = $this->lock($request);

            if ($locked->status === ApprovalStatus::Cancelled) {
                return $locked;
            }

            $this->assertPending($locked);
            $locked->update(['status' => ApprovalStatus::Cancelled, 'decided_at' => now()]);
            $this->audit->log('approval.cancelled', $locked, null, null, $requester);

            return $locked;
        });
    }

    private function decide(ApprovalRequest $request, User $admin, ApprovalStatus $status, ?string $reason): ApprovalRequest
    {
        $decided = DB::transaction(function () use ($request, $admin, $status, $reason): ?ApprovalRequest {
            $locked = $this->lock($request);

            // A repeated identical decision is a no-op, so a double click can never apply twice.
            if ($locked->status === $status) {
                return null;
            }

            $this->assertPending($locked);
            $locked->update([
                'status' => $status,
                'decided_by' => $admin->id,
                'decision_reason' => $reason,
                'decided_at' => now(),
            ]);

            if ($status === ApprovalStatus::Approved) {
                $this->handlers->for($locked->type)->apply($locked);
            }

            $this->audit->log("approval.{$status->value}", $locked, $reason, ['type' => $locked->type->value], $admin);

            return $locked;
        });

        if ($decided === null) {
            return $request->refresh();
        }

        $this->notifyRequester($decided);

        return $decided;
    }

    private function lock(ApprovalRequest $request): ApprovalRequest
    {
        return ApprovalRequest::query()->with('workspace')->lockForUpdate()->findOrFail($request->getKey());
    }

    private function assertPending(ApprovalRequest $request): void
    {
        if (! $request->isPending()) {
            throw new ApprovalStateException(__('messages.approval_not_pending'));
        }
    }

    private function notifyRequester(ApprovalRequest $request): void
    {
        $requester = $request->requester;

        if ($requester !== null) {
            $requester->notify(new ApprovalDecidedNotification($request));
        }
    }

    /**
     * Every active account allowed to decide requests (a super admin holds the
     * permission through its role).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    private function approvers(): \Illuminate\Database\Eloquent\Collection
    {
        $permission = AdminPermission::ApproveRequests->value;

        if (! Permission::query()->where('name', $permission)->where('guard_name', 'web')->exists()) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return User::query()->permission($permission)->get();
    }
}
