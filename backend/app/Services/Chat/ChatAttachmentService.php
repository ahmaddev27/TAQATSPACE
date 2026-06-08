<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores chat message attachments and resolves viewable URLs for them. Files
 * live on S3 in production (private bucket, accessed via short-lived signed
 * URLs) and on the local `public` disk in development, so the feature works in
 * both environments. The Firestore message only stores the returned metadata
 * (path/name/type/size); the file itself never touches Firestore.
 */
class ChatAttachmentService
{
    /** Where attachment keys live, so the URL endpoint can validate the prefix. */
    public const DIRECTORY = 'chat-attachments';

    public function __construct(
        private readonly FileUploadService $files,
    ) {}

    /**
     * Store an uploaded attachment under chat-attachments/{uid}/ and return the
     * metadata the client embeds in the Firestore message.
     *
     * @return array{path: string, name: string, type: string, size: int}
     */
    public function store(UploadedFile $file, string $uid): array
    {
        $disk = $this->disk();
        $path = $this->files->upload($file, self::DIRECTORY."/{$uid}", $disk);

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => (int) $file->getSize(),
        ];
    }

    /**
     * A viewable URL for a stored attachment: a short-lived signed URL on S3, or
     * the public URL on the local public disk. Null when the file is missing.
     */
    public function url(string $path): ?string
    {
        $disk = $this->disk();
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            return null;
        }

        if ($disk === 's3') {
            return $storage->temporaryUrl($path, now()->addHour());
        }

        return $storage->url($path);
    }

    /**
     * S3 when it is the default disk (servers), otherwise the local public disk
     * so attachments are web-accessible in development too.
     */
    private function disk(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'public';
    }
}
