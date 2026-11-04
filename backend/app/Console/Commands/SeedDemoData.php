<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Demo\DemoCleaner;
use App\Services\Demo\DemoDataSeeder;
use App\Services\Demo\DemoEnvironmentGuard;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Creates realistic dummy data for every role so the platform can be exercised
 * on staging. It refuses to run on a production host and never prints the
 * password it was given.
 */
class SeedDemoData extends Command
{
    protected $signature = 'demo:seed {--fresh : Delete the existing demo data first}';

    protected $description = 'Create (or reset with --fresh) demo accounts and data on a local, testing or staging host';

    public function handle(DemoEnvironmentGuard $guard, DemoCleaner $cleaner, DemoDataSeeder $seeder): int
    {
        try {
            $guard->assertAllowed($this->laravel->environment(), (string) config('app.url'));
            $password = $this->demoPassword();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $removed = $cleaner->clean();
            $this->line("Removed {$removed['users']} demo users and {$removed['workspaces']} demo workspaces.");
        } elseif ($seeder->hasDemoData()) {
            $this->warn('Demo data already exists. Run with --fresh to rebuild it.');

            return self::SUCCESS;
        }

        $created = $seeder->seed($password);
        $this->info("Seeded {$created['users']} demo users and {$created['workspaces']} demo workspaces.");

        return self::SUCCESS;
    }

    /** env() reads the process environment even when the config is cached, which is how the workflow passes it. */
    private function demoPassword(): string
    {
        $password = env('DEMO_PASSWORD');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('DEMO_PASSWORD is not set; refusing to create accounts without it.');
        }

        return $password;
    }
}
