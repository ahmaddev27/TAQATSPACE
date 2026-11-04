<?php

declare(strict_types=1);

namespace App\Services\Demo;

use Illuminate\Support\Str;

/**
 * The single definition of what makes a row "demo": the account address domain
 * and the workspace slug prefix. Everything that seeds, cleans or mutes demo
 * data goes through here so the marking cannot drift.
 */
final class DemoMarker
{
    public static function emailDomain(): string
    {
        return (string) config('demo.email_domain');
    }

    public static function slugPrefix(): string
    {
        return (string) config('demo.slug_prefix');
    }

    public static function emailFor(string $localPart): string
    {
        return $localPart.'@'.self::emailDomain();
    }

    public static function isDemoEmail(?string $email): bool
    {
        return $email !== null && Str::endsWith(Str::lower($email), '@'.self::emailDomain());
    }

    public static function isDemoSlug(?string $slug): bool
    {
        return $slug !== null && Str::startsWith($slug, self::slugPrefix());
    }
}
