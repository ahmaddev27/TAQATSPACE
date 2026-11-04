<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\FinancePermission;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\Channels\SafeMailChannel;
use App\Services\Approvals\ApprovalHandlerRegistry;
use App\Services\Approvals\Handlers\CapacityApprovalHandler;
use App\Services\Approvals\Handlers\PricingApprovalHandler;
use App\Services\Approvals\Handlers\WorkingHoursApprovalHandler;
use App\Services\Finance\FinanceAccess;
use App\Services\Workspace\Reset\Steps\BookingsResetStep;
use App\Services\Workspace\Reset\Steps\ExpensesResetStep;
use App\Services\Workspace\Reset\Steps\InvoicesResetStep;
use App\Services\Workspace\Reset\Steps\MembersResetStep;
use App\Services\Workspace\Reset\Steps\PosResetStep;
use App\Services\Workspace\Reset\WorkspaceResetter;
use App\Support\Tenancy\CurrentWorkspaceContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentWorkspaceContext::class);

        // Add a handler here when a new ApprovalType is introduced.
        $this->app->singleton(ApprovalHandlerRegistry::class, fn ($app) => new ApprovalHandlerRegistry([
            $app->make(CapacityApprovalHandler::class),
            $app->make(WorkingHoursApprovalHandler::class),
            $app->make(PricingApprovalHandler::class),
        ]));

        // Later modules (network, finance, GSG) tag their own WorkspaceResetStep
        // with `workspace.reset` from their provider; nothing here changes.
        $this->app->tag([
            InvoicesResetStep::class,
            BookingsResetStep::class,
            MembersResetStep::class,
            ExpensesResetStep::class,
            PosResetStep::class,
        ], 'workspace.reset');

        $this->app->singleton(
            WorkspaceResetter::class,
            fn ($app) => new WorkspaceResetter($app->tagged('workspace.reset')),
        );
    }

    public function boot(): void
    {
        // Make the `mail` notification channel resilient: an email transport
        // failure (e.g. an unverified SMTP sender) must never fail the queued
        // notification job, which would drop the in-app + push channels and
        // duplicate the notification on retry. Applies to every notification.
        $this->app->make(ChannelManager::class)->extend(
            'mail',
            static fn ($app) => $app->make(SafeMailChannel::class),
        );

        // Routers sync every ~50 s; 12/min leaves room for retries. Keyed per
        // router (set by the `router` middleware), never per IP, since many
        // routers can sit behind one carrier NAT.
        RateLimiter::for('router', static fn (Request $request): Limit => Limit::perMinute(12)
            ->by((string) ($request->attributes->get('router')?->getKey() ?? $request->ip())));

        Gate::define('manage-workspace', static function (User $user, Workspace $workspace): bool {
            return $user->id === $workspace->owner_id || $user->isAdmin();
        });

        // POS (café) access to a workspace: its owner and platform admins always
        // pass; a cashier passes only for the single workspace it is bound to.
        // Deliberately SEPARATE from `manage-workspace` so a cashier never gains
        // the owner-only surfaces (seats, logo, messaging, broadcasts) that gate
        // guards — the POS controllers additionally check the specific
        // PosPermission the action needs.
        Gate::define('access-pos', static function (User $user, Workspace $workspace): bool {
            return $user->id === $workspace->owner_id
                || $user->isAdmin()
                || ($user->isCashier() && (string) $user->workspace_id === (string) $workspace->id);
        });

        // `can:finance.<permission>` / Gate::allows('finance.finance_collect') delegate to
        // FinanceAccess, so policies and the route middleware share one rule.
        foreach (FinancePermission::cases() as $permission) {
            Gate::define(
                'finance.'.$permission->value,
                static fn (User $user): bool => app(FinanceAccess::class)->can($user, $permission),
            );
        }

        // Controls access to the Scramble API docs UI (`/docs/api`) and spec
        // (`/docs/api.json`). The API surface is already discoverable from the
        // public SPA bundle, so the docs add little attack surface — allow by
        // default and flip API_DOCS_ENABLED=false to lock them down.
        Gate::define('viewApiDocs', static function (?User $user = null): bool {
            return (bool) config('scramble.docs_enabled', true);
        });
    }
}
