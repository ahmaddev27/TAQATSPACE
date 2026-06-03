<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Models\Workspace;
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
        Gate::define('manage-workspace', static function (User $user, Workspace $workspace): bool {
            return $user->id === $workspace->owner_id || $user->isAdmin();
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
