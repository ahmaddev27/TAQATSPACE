<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Invoices v2: document/payment statuses, frozen totals, line items and a
 * gap-free numbering sequence. Legacy invoices keep their `status` and get the
 * equivalent two new statuses plus a single line, so both models can coexist.
 */
return new class extends Migration
{
    /** Legacy status => [doc_status, payment_status]. */
    private const STATUS_MAP = [
        'pending' => ['issued', 'unpaid'],
        'overdue' => ['issued', 'unpaid'],
        'under_review' => ['issued', 'unpaid'],
        'payment_rejected' => ['issued', 'unpaid'],
        'partially_paid' => ['issued', 'partial'],
        'paid' => ['issued', 'paid'],
        'cancelled' => ['void', 'unpaid'],
    ];

    public function up(): void
    {
        $this->createSequencesAndLines();
        $this->extendInvoices();
        $this->backfill();
        $this->linkUnbilledCharges();
    }

    public function down(): void
    {
        Schema::table('unbilled_charges', function (Blueprint $table): void {
            $table->dropForeign(['invoice_line_id']);
        });
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('document_sequences');
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['workspace_id']);
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['workspace_id', 'doc_status']);
            $table->dropIndex(['workspace_id', 'payment_status']);
            $table->dropIndex(['customer_id']);
            $table->dropColumn([
                'workspace_id', 'customer_id', 'doc_status', 'payment_status', 'subtotal', 'tax_rate',
                'tax_amount', 'total', 'period_start', 'period_end', 'issued_at', 'voided_at', 'void_reason',
            ]);
        });
    }

    private function createSequencesAndLines(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('prefix', 40);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique(['workspace_id', 'type']);
        });

        Schema::create('invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->foreignUuid('unbilled_charge_id')->nullable()->constrained('unbilled_charges')->nullOnDelete();
            $table->foreignUuid('catalog_item_id')->nullable()->constrained('catalog_items')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order']);
        });
    }

    private function extendInvoices(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignUuid('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('doc_status', 20)->default('issued');
            $table->string('payment_status', 20)->default('unpaid');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();

            $table->index(['workspace_id', 'doc_status']);
            $table->index(['workspace_id', 'payment_status']);
        });

        // Drafts have no number or subscription yet.
        Schema::table('invoices', function (Blueprint $table): void {
            $table->uuid('subscription_id')->nullable()->change();
            $table->string('invoice_number')->nullable()->change();
        });
    }

    private function backfill(): void
    {
        foreach (self::STATUS_MAP as $status => [$doc, $payment]) {
            DB::table('invoices')->where('status', $status)->update(['doc_status' => $doc, 'payment_status' => $payment]);
        }

        DB::table('invoices')->whereNull('workspace_id')->update([
            'workspace_id' => DB::table('subscriptions')->select('workspace_id')->whereColumn('subscriptions.id', 'invoices.subscription_id'),
            'customer_id' => DB::table('subscriptions')->select('member_id')->whereColumn('subscriptions.id', 'invoices.subscription_id'),
        ]);

        DB::table('invoices')->update([
            'subtotal' => DB::raw('amount'),
            'total' => DB::raw('amount'),
            'issued_at' => DB::raw('created_at'),
        ]);
        DB::table('invoices')->where('doc_status', 'void')->update(['voided_at' => DB::raw('updated_at')]);

        $this->backfillLines();
    }

    private function backfillLines(): void
    {
        DB::table('invoices')->select(['id', 'invoice_number', 'amount', 'created_at'])->orderBy('id')
            ->chunk(500, function ($invoices): void {
                DB::table('invoice_lines')->insert($invoices->map(fn (object $invoice): array => [
                    'id' => (string) Str::uuid(),
                    'invoice_id' => $invoice->id,
                    'description' => 'Invoice '.$invoice->invoice_number,
                    'qty' => '1.00',
                    'unit_price' => $invoice->amount,
                    'amount' => $invoice->amount,
                    'sort_order' => 0,
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->created_at,
                ])->all());
            });
    }

    private function linkUnbilledCharges(): void
    {
        Schema::table('unbilled_charges', function (Blueprint $table): void {
            $table->foreign('invoice_line_id')->references('id')->on('invoice_lines')->nullOnDelete();
        });
    }
};
