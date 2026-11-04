<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single operating expense recorded by a workspace owner.
 *
 * @property string $workspace_id
 */
class Expense extends Model
{
    /** @use HasFactory<\Database\Factories\ExpenseFactory> */
    use HasFactory, HasUuids;

    protected $table = 'workspace_expenses';

    /** @var list<string> */
    protected $fillable = [
        'workspace_id',
        'title',
        'category',
        'catalog_category_id',
        'amount',
        'method',
        'reference',
        'spent_on',
        'notes',
        'voided_at',
        'void_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'voided_at' => 'datetime',
            'spent_on' => 'date',
        ];
    }

    /** @return BelongsTo<CatalogCategory, $this> */
    public function catalogCategory(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class);
    }

    /** @param Builder<static> $query */
    public function scopeNotVoided(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('voided_at'));
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
