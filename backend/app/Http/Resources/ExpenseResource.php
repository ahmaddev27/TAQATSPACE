<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a single workspace expense for the owner management view.
 *
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category->value,
            'catalog_category_id' => $this->catalog_category_id,
            'catalog_category' => $this->whenLoaded('catalogCategory', fn (): array => [
                'id' => $this->catalogCategory->id,
                'code' => $this->catalogCategory->code,
                'name_ar' => $this->catalogCategory->name_ar,
                'name_en' => $this->catalogCategory->name_en,
            ]),
            'amount' => $this->amount,
            'method' => $this->method?->value,
            'reference' => $this->reference,
            'spent_on' => $this->spent_on?->toDateString(),
            'notes' => $this->notes,
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
