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

        return $file->storeAs($directory, $filename, [
            'disk' => $disk,
            'visibility' => $visibility,
        ]);
    }
}
