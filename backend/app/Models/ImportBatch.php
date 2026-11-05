<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * An uploaded import file and where it stands.
 *
 * @property string $workspace_id
 * @property ImportType $type
 * @property string $file_hash
 * @property ImportStatus $status
 * @property int $row_count
 * @property array<int, array{row: int, column: string|null, message: string}>|null $errors
 * @property string|null $payload_path
 */
class ImportBatch extends Model
{
    use BelongsToWorkspace, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'type',
        'file_hash',
        'status',
        'row_count',
        'errors',
        'payload_path',
        'created_by',
        'committed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ImportType::class,
            'status' => ImportStatus::class,
            'row_count' => 'integer',
            'errors' => 'array',
            'committed_at' => 'datetime',
        ];
    }

    public function isCommitted(): bool
    {
        return $this->status === ImportStatus::Committed;
    }
}
