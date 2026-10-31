<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Support\Str;

class WorkspaceIdentityGenerator
{
    public const CODE_LENGTH = 8;

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const FALLBACK_SLUG = 'workspace';

    /** Leaves room for a numeric collision suffix inside the 191-char column. */
    private const MAX_BASE_LENGTH = 150;

    /**
     * An ASCII slug derived from the name, suffixed (-2, -3, ...) when taken.
     * Soft-deleted rows are included because the unique index covers them.
     */
    public function generateSlug(string $name): string
    {
        $base = trim(Str::limit(Str::slug($name), self::MAX_BASE_LENGTH, ''), '-');
        $base = $base === '' ? self::FALLBACK_SLUG : $base;

        $taken = Workspace::withTrashed()
            ->where('slug', 'like', $base.'%')
            ->pluck('slug')
            ->all();

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        $suffix = 2;
        while (in_array($base.'-'.$suffix, $taken, true)) {
            $suffix++;
        }

        return $base.'-'.$suffix;
    }

    /** A unique 8-character base32 code from a CSPRNG. */
    public function generateCode(): string
    {
        do {
            $code = $this->randomCode();
        } while (Workspace::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    private function randomCode(): string
    {
        $bytes = random_bytes(self::CODE_LENGTH);
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::BASE32_ALPHABET[ord($bytes[$i]) & 31];
        }

        return $code;
    }
}
