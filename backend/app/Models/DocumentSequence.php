<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Counter behind gap-free document numbers; one row per workspace and type.
 * Only DocumentNumberService advances it, under a row lock.
 */
class DocumentSequence extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'type', 'prefix', 'next_value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['next_value' => 'integer'];
    }
}
