<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    /**
     * Resolve a stored media path (workspace photo, avatar) to a viewable URL.
     *
     * On S3 we return a presigned temporary URL, so images load even while the
     * bucket blocks public access — no public bucket policy required. On local
     * disks we return the public URL; unknown/seed paths pass through unchanged
     * (the frontend falls back to a placeholder when they 404).
     */
    public static function resolve(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $diskName = (string) config('filesystems.media', 'public');
        $disk = Storage::disk($diskName);

        if ($diskName === 's3') {
            // Presigned URL generation is a local signature computation (no API
            // call); non-existent seed paths still sign and simply 404 on fetch.
            return $disk->temporaryUrl($path, now()->addDay());
        }

        return $disk->exists($path) ? $disk->url($path) : $path;
    }
}
