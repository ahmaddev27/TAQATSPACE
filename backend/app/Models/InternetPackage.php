<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InternetPackage extends Model
{
    /** @use HasFactory<\Database\Factories\InternetPackageFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'name',
        'speed_mbps',
        'price',
        'data_limit_gb',
        'is_unlimited',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'speed_mbps' => 'integer',
            'price' => 'decimal:2',
            'data_limit_gb' => 'integer',
            'is_unlimited' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'member_package', 'package_id', 'member_id')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }
}
