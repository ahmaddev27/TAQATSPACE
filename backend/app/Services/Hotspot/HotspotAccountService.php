<?php

declare(strict_types=1);

namespace App\Services\Hotspot;

use App\Enums\HotspotAccountStatus;
use App\Enums\RouterCommandVerb;
use App\Enums\RouterStatus;
use App\Models\HotspotAccount;
use App\Models\InternetPackage;
use App\Models\Router;
use App\Models\Subscription;
use App\Services\Audit\AuditLogger;
use App\Services\Router\RouterCommandService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Owns the hotspot account of a subscription and mirrors its lifecycle onto
 * the workspace's routers through the command queue. It does not need a
 * router to exist: the account is saved either way and the push simply has no
 * target until a router is added. Passwords reach the queue only inside the
 * encrypted command payload and are never written to the audit log.
 */
class HotspotAccountService
{
    public function __construct(
        private readonly RouterCommandService $commands,
        private readonly HotspotProfileMapper $profiles,
        private readonly AuditLogger $audit,
    ) {}

    /** Create the account or rotate its password, then push the profile and the user. */
    public function provision(Subscription $subscription, string $password, ?InternetPackage $package = null): HotspotAccount
    {
        $account = $this->accountOf($subscription);
        $package ??= $this->packageOf($subscription);
        $profile = $package === null ? null : $this->profiles->fromPackage($package, $this->validityDays($subscription));

        $account ??= new HotspotAccount(['workspace_id' => $subscription->workspace_id, 'subscription_id' => $subscription->getKey()]);
        $account->fill([
            'username' => $account->username ?? $this->uniqueUsername($subscription),
            'password_enc' => $password,
            'profile' => $profile?->name ?? $account->profile,
            'status' => HotspotAccountStatus::Active,
            'provisioned_at' => now(),
        ])->save();

        $routers = $this->routersOf([$subscription->workspace_id]);
        foreach ($routers->get($subscription->workspace_id, []) as $router) {
            if ($profile !== null) {
                $this->commands->enqueue($router, RouterCommandVerb::UpsertProfile, ['name' => $profile->name, 'rate_limit' => $profile->rateLimit]);
            }
            $this->pushUser($router, $account);
        }

        $this->audit->log('network.hotspot_account.provisioned', $account, changes: $this->summary($account, $routers->get($subscription->workspace_id, [])));

        return $account;
    }

    /** Re-enable the account (renewal or reactivation) and push the user again. */
    public function resume(Subscription $subscription): ?HotspotAccount
    {
        $account = $this->accountOf($subscription);

        if ($account === null) {
            return null;
        }

        $account->update(['status' => HotspotAccountStatus::Active]);
        $routers = $this->routersOf([$account->workspace_id])->get($account->workspace_id, []);

        foreach ($routers as $router) {
            $this->pushUser($router, $account);
        }

        $this->audit->log('network.hotspot_account.resumed', $account, changes: $this->summary($account, $routers));

        return $account;
    }

    public function suspend(Subscription $subscription): void
    {
        $this->suspendMany(collect([$subscription]));
    }

    /**
     * Disable the accounts of several subscriptions with batched lookups, so the
     * expiry job does not query per subscription.
     *
     * @param  Collection<int, Subscription>  $subscriptions
     */
    public function suspendMany(Collection $subscriptions): void
    {
        $accounts = HotspotAccount::query()->withoutWorkspaceScope()
            ->whereIn('subscription_id', $subscriptions->map(fn (Subscription $s) => $s->getKey())->all())
            ->where('status', HotspotAccountStatus::Active->value)
            ->get();

        if ($accounts->isEmpty()) {
            return;
        }

        HotspotAccount::query()->withoutWorkspaceScope()
            ->whereIn('id', $accounts->modelKeys())
            ->update(['status' => HotspotAccountStatus::Suspended->value]);

        $routers = $this->routersOf($accounts->pluck('workspace_id')->unique()->all());

        foreach ($accounts as $account) {
            $targets = $routers->get($account->workspace_id, []);
            $this->pushDisable($targets, $account);
            $this->audit->log('network.hotspot_account.suspended', $account, changes: $this->summary($account, $targets));
        }
    }

    /** Disable the user on the routers and free the username. */
    public function remove(Subscription $subscription): void
    {
        $account = $this->accountOf($subscription);

        if ($account === null) {
            return;
        }

        $targets = $this->routersOf([$account->workspace_id])->get($account->workspace_id, []);
        $this->pushDisable($targets, $account);
        $this->audit->log('network.hotspot_account.removed', $account, changes: $this->summary($account, $targets));
        $account->delete();
    }

    /** @param  iterable<Router>  $routers */
    private function pushDisable(iterable $routers, HotspotAccount $account): void
    {
        foreach ($routers as $router) {
            $this->commands->enqueue($router, RouterCommandVerb::DisableUser, ['username' => $account->username]);
            $this->commands->enqueue($router, RouterCommandVerb::RemoveActive, ['username' => $account->username]);
        }
    }

    private function pushUser(Router $router, HotspotAccount $account): void
    {
        $this->commands->enqueue($router, RouterCommandVerb::UpsertUser, [
            'username' => $account->username,
            'password' => $account->password_enc,
            'profile' => $account->profile,
        ]);
    }

    /**
     * @param  iterable<Router>  $routers
     * @return array<string, mixed>
     */
    private function summary(HotspotAccount $account, iterable $routers): array
    {
        return [
            'username' => $account->username,
            'profile' => $account->profile,
            'status' => $account->status->value,
            'routers' => is_countable($routers) ? count($routers) : iterator_count($routers),
        ];
    }

    private function accountOf(Subscription $subscription): ?HotspotAccount
    {
        return HotspotAccount::query()->withoutWorkspaceScope()
            ->where('subscription_id', $subscription->getKey())
            ->first();
    }

    /**
     * Routers that can still be told what to do, grouped by workspace.
     *
     * @param  list<string>  $workspaceIds
     * @return Collection<string, Collection<int, Router>>
     */
    private function routersOf(array $workspaceIds): Collection
    {
        return Router::query()->withoutWorkspaceScope()
            ->whereIn('workspace_id', $workspaceIds)
            ->where('status', '!=', RouterStatus::Disabled->value)
            ->get()
            ->groupBy('workspace_id');
    }

    /** The member's package in the subscription's workspace, if one is assigned. */
    private function packageOf(Subscription $subscription): ?InternetPackage
    {
        return InternetPackage::query()
            ->where('workspace_id', $subscription->workspace_id)
            ->whereHas('members', fn ($members) => $members->where('users.id', $subscription->member_id))
            ->first();
    }

    private function validityDays(Subscription $subscription): ?int
    {
        if ($subscription->start_date === null || $subscription->end_date === null) {
            return null;
        }

        return (int) $subscription->start_date->diffInDays($subscription->end_date);
    }

    /** A readable login from the member's name, unique within the workspace. */
    private function uniqueUsername(Subscription $subscription): string
    {
        $member = $subscription->member;
        $base = Str::of((string) ($member?->name ?? $member?->email ?? 'user'))
            ->lower()->ascii()->replaceMatches('/[^a-z0-9]/', '')->substr(0, 12)->value();
        $candidate = $base = $base === '' ? 'user' : $base;

        while (HotspotAccount::query()->withoutWorkspaceScope()
            ->where('workspace_id', $subscription->workspace_id)->where('username', $candidate)->exists()) {
            $candidate = $base.Str::lower(Str::random(3));
        }

        return $candidate;
    }
}
