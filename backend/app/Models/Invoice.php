<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'subscription_id',
        'amount',
        'due_date',
        'paid_at',
        'status',
        'invoice_number',
        'invoice_pdf_path',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
