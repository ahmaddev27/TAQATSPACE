<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Store an uploaded file and return its disk-relative path.
     *
     * The disk defaults to the configured default filesystem (`local` in dev,
     * `s3` in production once the AWS SDK is installed on the server). Owner
     * documents/IDs are stored privately.
     */
    public function upload(
        UploadedFile $file,
        string $directory,
        ?string $disk = null,
        string $visibility = 'private',
    ): string {
        $disk ??= (string) config('filesystems.default');

        $filename = Str::uuid()->toString().'.'.self::safeExtension($file);

        // Modern S3 buckets enforce bucket-owner ownership (ACLs disabled), so a
        // per-object ACL request fails. Access is governed by the bucket policy
        // instead — only pass visibility for local disks where it is meaningful.
        $options = ['disk' => $disk];
        if ($disk !== 's3') {
            $options['visibility'] = $visibility;
        }

        return $file->storeAs($directory, $filename, $options);
    }

    /**
     * A safe storage extension derived from the file's actual content (MIME),
     * never the client-supplied name — so a content-valid polyglot uploaded as
     * "evil.php" is stored as its true type (e.g. .png) and can't be executed on
     * a web-served disk. Falls back to a sanitised client extension for content
     * types the guesser can't map (e.g. some Office formats), then "bin".
     */
    private static function safeExtension(UploadedFile $file): string
    {
        $extension = $file->guessExtension();

        if ($extension === null || $extension === '') {
            $extension = strtolower(
                (string) preg_replace('/[^a-z0-9]/i', '', (string) $file->getClientOriginalExtension()),
            );
        }

        return $extension !== '' ? $extension : 'bin';
    }
}
