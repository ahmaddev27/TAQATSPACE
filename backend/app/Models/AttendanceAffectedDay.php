<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A workspace-local day whose sessions changed since attendance was last
 * rebuilt for it. Deliberately not tenant-scoped: the scheduler drains it
 * across all workspaces.
 *
 * @property int $id
 * @property string $workspace_id
 * @property string $date Y-m-d
 * @property CarbonInterface $marked_at
 */
class AttendanceAffectedDay extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'date', 'marked_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'marked_at' => 'datetime'];
    }
}
