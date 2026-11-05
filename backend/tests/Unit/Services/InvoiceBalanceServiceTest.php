<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Invoice;
use App\Services\Finance\InvoiceBalanceService;
use PHPUnit\Framework\TestCase;

/** The outstanding-balance arithmetic, with no database involved. */
class InvoiceBalanceServiceTest extends TestCase
{
    private function invoice(string $amount, string $paid, string $credited): Invoice
    {
        return (new Invoice)->forceFill(['amount' => $amount, 'amount_paid' => $paid, 'credited_amount' => $credited]);
    }

    public function test_unpaid_is_total_less_credit_less_collected(): void
    {
        $service = new InvoiceBalanceService;

        $this->assertSame('45.00', $service->unpaid($this->invoice('100.00', '25.00', '30.00')));
        $this->assertSame('100.00', $service->unpaid($this->invoice('100.00', '0.00', '0.00')));
    }

    public function test_unpaid_never_goes_negative_when_the_credit_covers_money_already_paid(): void
    {
        $this->assertSame('0.00', (new InvoiceBalanceService)->unpaid($this->invoice('100.00', '60.00', '100.00')));
    }
}
