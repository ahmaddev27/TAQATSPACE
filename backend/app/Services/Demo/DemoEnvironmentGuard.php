<?php

declare(strict_types=1);

namespace App\Services\Demo;

use RuntimeException;

/**
 * Decides whether demo data may be created here. The repository is public and
 * the command creates accounts with a known password, so a production host must
 * be refused whatever APP_ENV claims, and staging is recognised by its URL
 * because the server does not always run with APP_ENV=staging.
 */
final class DemoEnvironmentGuard
{
    /** @throws RuntimeException when the environment is not allowed */
    public function assertAllowed(string $environment, string $appUrl): void
    {
        $host = $this->hostOf($appUrl);

        if ($host === '' || in_array($host, (array) config('demo.forbidden_hosts'), true)) {
            throw new RuntimeException('Refusing to seed demo data: the APP_URL host is a production host or is not set.');
        }

        if (in_array($environment, (array) config('demo.allowed_environments'), true)) {
            return;
        }

        if (str_contains($host, (string) config('demo.staging_host_marker'))) {
            return;
        }

        throw new RuntimeException('Refusing to seed demo data: this is neither a local/testing environment nor a staging host.');
    }

    private function hostOf(string $appUrl): string
    {
        $host = parse_url($appUrl, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }
}
