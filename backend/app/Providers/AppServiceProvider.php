<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Models\Workspace;
use App\Notifications\Channels\SafeMailChannel;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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

        // Controls access to the Scramble API docs UI (`/docs/api`) and spec
        // (`/docs/api.json`). The API surface is already discoverable from the
        // public SPA bundle, so the docs add little attack surface — allow by
        // default and flip API_DOCS_ENABLED=false to lock them down.
        Gate::define('viewApiDocs', static function (?User $user = null): bool {
            return (bool) config('scramble.docs_enabled', true);
        });
    }
}
