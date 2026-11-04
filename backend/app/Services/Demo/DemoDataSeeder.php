<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder
{
    public function __construct(private readonly Container $container) {}

    public function hasDemoData(): bool
    {
        return User::query()->where('email', 'like', '%@'.DemoMarker::emailDomain())->exists();
    }

    /** @return array{users: int, workspaces: int} counts for the command's report */
    public function seed(string $plainPassword): array
    {
        $context = new DemoContext(Hash::make($plainPassword), DemoMarker::routerKeyFor($plainPassword));

        DB::transaction(function () use ($context): void {
            foreach ((array) config('demo.steps') as $stepClass) {
                $this->container->make($stepClass)->seed($context);
            }
        });

        return [
            'users' => count($context->users()),
            'workspaces' => count($context->workspaces()),
        ];
    }
}
