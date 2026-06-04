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

        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();

        // Modern S3 buckets enforce bucket-owner ownership (ACLs disabled), so a
        // per-object ACL request fails. Access is governed by the bucket policy
        // instead — only pass visibility for local disks where it is meaningful.
        $options = ['disk' => $disk];
        if ($disk !== 's3') {
            $options['visibility'] = $visibility;
        }

        return $file->storeAs($directory, $filename, $options);
    }
}
