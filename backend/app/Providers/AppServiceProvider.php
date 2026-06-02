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
    }
}
