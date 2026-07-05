<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Enums\PosPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CashierInvitation;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\CashierInvitationNotification;
use App\Services\Admin\AdminManagementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Owner-facing administration of a workspace's cashier/café staff: email
 * invitations, SSO-onboarding acceptance, the staff directory, and
 * deactivation. Mirrors {@see AdminManagementService} but
 * scoped to a single workspace via `users.workspace_id`.
 *
 * Acceptance follows the SSO model: the invitee signs in to Taqat normally
 * (email verified by the IdP) and, during onboarding, accepts or declines a
 * pending invitation matching their address — there is no local password.
 */
class CashierManagementService
{
    /** How long an emailed invitation stays valid. */
    private const INVITE_TTL_DAYS = 7;

    /**
     * Create + email a cashier invitation for a workspace. The invitation is just
     * a record: the invitee signs in via SSO and accepts it during onboarding.
     *
     * An email is rejected only when it already belongs to an ONBOARDED account —
     * a not-yet-onboarded SSO account (or no account at all) is fine, since that
     * account can still adopt the cashier role during onboarding.
     *
     * @param  array{email: string, name?: string|null, permissions?: array<int, string>|null}  $data
     */
    public function invite(Workspace $workspace, User $inviter, array $data): CashierInvitation
    {
        $email = mb_strtolower(trim($data['email']));

        $alreadyOnboarded = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNotNull('onboarding_completed_at')
            ->exists();

        if ($alreadyOnboarded) {
            throw new RuntimeException(__('messages.cashier_email_taken'));
        }

        $permissions = $this->sanitizePermissions($data['permissions'] ?? null);
        $token = Str::random(48);

        $invitation = DB::transaction(function () use ($workspace, $inviter, $email, $data, $permissions, $token): CashierInvitation {
            // A workspace keeps at most one open invite per email — supersede any
            // earlier pending one so re-inviting simply refreshes it.
            CashierInvitation::query()
                ->where('workspace_id', $workspace->id)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->whereNull('accepted_at')
                ->delete();

            return CashierInvitation::query()->create([
                'workspace_id' => $workspace->id,
                'invited_by' => $inviter->id,
                'email' => $email,
                'name' => $data['name'] ?? null,
                'token_hash' => $this->hashToken($token),
                'permissions' => $permissions,
                'expires_at' => Carbon::now()->addDays(self::INVITE_TTL_DAYS),
            ]);
        });

        Notification::route('mail', $email)->notify(
            new CashierInvitationNotification($workspace, $invitation->name)
        );

        return $invitation;
    }

    /**
     * The newest still-open invitation addressed to the given email, if any. Used
     * during onboarding to offer the authenticated (IdP-verified) user an
     * accept/decline choice.
     */
    public function pendingForEmail(string $email): ?CashierInvitation
    {
        return CashierInvitation::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();
    }

    /**
     * Accept an invitation for the authenticated (SSO) user: adopt the cashier
     * role + workspace + permission grant, complete onboarding, and consume the
     * invite. The user's IdP-verified email must match the invitation's.
     */
    public function acceptForUser(User $user, CashierInvitation $invitation): User
    {
        $matches = $invitation->isPending()
            && strcasecmp($invitation->email, (string) $user->email) === 0
            && $user->needsOnboarding();

        if (! $matches) {
            throw new RuntimeException(__('messages.cashier_invite_invalid'));
        }

        return DB::transaction(function () use ($user, $invitation): User {
            $user->forceFill([
                'role' => UserRole::Cashier->value,
                'workspace_id' => $invitation->workspace_id,
                'status' => UserStatus::Active->value,
                'onboarding_completed_at' => now(),
            ])->save();

            $user->syncRoles([UserRole::Cashier->value]);
            $user->syncPermissions($invitation->permissions ?? PosPermission::defaultsForCashier());
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $invitation->forceFill([
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
            ])->save();

            return $user->fresh() ?? $user;
        });
    }

    /**
     * Decline an invitation for the authenticated user, freeing them to proceed
     * to the normal freelancer/owner onboarding choice.
     */
    public function declineForUser(User $user, CashierInvitation $invitation): void
    {
        $matches = $invitation->isPending()
            && strcasecmp($invitation->email, (string) $user->email) === 0;

        if (! $matches) {
            abort(404, __('messages.cashier_invite_invalid'));
        }

        $invitation->forceFill(['declined_at' => now()])->save();
    }

    /**
     * Update a cashier's POS permission grant (owner curates what they can do).
     *
     * @param  array<int, string>  $permissions
     */
    public function updatePermissions(User $cashier, Workspace $workspace, array $permissions): User
    {
        $this->ensureBelongsToWorkspace($cashier, $workspace);

        $cashier->syncPermissions($this->sanitizePermissions($permissions) ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $cashier->fresh() ?? $cashier;
    }

    /**
     * Suspend a cashier account (preferred over deletion so order history keeps
     * its cashier reference).
     */
    public function deactivate(User $cashier, Workspace $workspace): User
    {
        $this->ensureBelongsToWorkspace($cashier, $workspace);

        $cashier->forceFill(['status' => UserStatus::Suspended->value])->save();

        return $cashier->fresh() ?? $cashier;
    }

    /** Reject (404) a user that is not a cashier of the given workspace. */
    private function ensureBelongsToWorkspace(User $cashier, Workspace $workspace): void
    {
        if (! $cashier->isCashier() || (string) $cashier->workspace_id !== (string) $workspace->id) {
            abort(404, __('messages.cashier_not_found'));
        }
    }

    /**
     * Keep only recognised POS permission names; null when nothing valid was
     * given so callers fall back to the cashier defaults.
     *
     * @param  array<int, string>|null  $permissions
     * @return array<int, string>|null
     */
    private function sanitizePermissions(?array $permissions): ?array
    {
        if ($permissions === null) {
            return null;
        }

        $valid = array_values(array_intersect($permissions, PosPermission::values()));

        return $valid === [] ? null : $valid;
    }

    /** Deterministic hash for token lookup (the raw token is never stored). */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
