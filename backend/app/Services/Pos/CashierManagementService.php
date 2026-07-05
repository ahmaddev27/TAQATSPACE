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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Owner-facing administration of a workspace's cashier/café staff: email
 * invitations, acceptance (account provisioning), the staff directory, and
 * deactivation. Mirrors {@see AdminManagementService} but
 * scoped to a single workspace via `users.workspace_id`.
 */
class CashierManagementService
{
    /** How long an emailed invitation stays valid. */
    private const INVITE_TTL_DAYS = 7;

    /**
     * Create + email a cashier invitation for a workspace. The invitee follows
     * the tokenised link to set a password; no account exists until acceptance.
     *
     * @param  array{email: string, name?: string|null, permissions?: array<int, string>|null}  $data
     */
    public function invite(Workspace $workspace, User $inviter, array $data): CashierInvitation
    {
        $email = mb_strtolower(trim($data['email']));

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw new RuntimeException(__('messages.cashier_email_taken'));
        }

        $permissions = $this->sanitizePermissions($data['permissions'] ?? null);
        $token = Str::random(48);

        $invitation = DB::transaction(function () use ($workspace, $inviter, $email, $data, $permissions, $token): CashierInvitation {
            // A workspace keeps at most one open invite per email — supersede any
            // earlier pending one so re-inviting simply refreshes the link.
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
            new CashierInvitationNotification($workspace, $token, $invitation->name)
        );

        return $invitation;
    }

    /**
     * Accept an invitation: provision the cashier account, apply its role +
     * permission grant, consume the invite, and return the account with a fresh
     * API token for immediate sign-in.
     *
     * @param  array{name: string, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function accept(string $token, array $data): array
    {
        $invitation = CashierInvitation::query()
            ->where('token_hash', $this->hashToken($token))
            ->first();

        if ($invitation === null || ! $invitation->isPending()) {
            throw new RuntimeException(__('messages.cashier_invite_invalid'));
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$invitation->email])->exists()) {
            throw new RuntimeException(__('messages.cashier_email_taken'));
        }

        return DB::transaction(function () use ($invitation, $data): array {
            $cashier = User::query()->create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
                'role' => UserRole::Cashier->value,
                'workspace_id' => $invitation->workspace_id,
                'status' => UserStatus::Active->value,
                'email_verified_at' => now(),
                'onboarding_completed_at' => now(),
            ]);

            $cashier->syncRoles([UserRole::Cashier->value]);
            $cashier->syncPermissions($invitation->permissions ?? PosPermission::defaultsForCashier());
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $invitation->forceFill([
                'accepted_at' => now(),
                'accepted_user_id' => $cashier->id,
            ])->save();

            return [
                'user' => $cashier->fresh() ?? $cashier,
                'token' => $cashier->createToken('cashier')->plainTextToken,
            ];
        });
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
