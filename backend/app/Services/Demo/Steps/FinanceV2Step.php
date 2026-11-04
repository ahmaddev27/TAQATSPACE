<?php

declare(strict_types=1);

namespace App\Services\Demo\Steps;

use App\Enums\BillingMode;
use App\Enums\CatalogKind;
use App\Enums\CatalogUnit;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\PricingMethod;
use App\Enums\UnbilledChargeSource;
use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Receipt;
use App\Models\Subscription;
use App\Models\UnbilledCharge;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Demo\DemoContext;
use App\Services\Demo\DemoSeedStep;
use App\Services\ExpenseService;
use App\Services\Finance\CatalogCategoryService;
use App\Services\Finance\CatalogItemService;
use App\Services\Finance\InvoiceLifecycleService;
use App\Services\Finance\PlanService;
use App\Services\Finance\ReceiptService;
use App\Services\Finance\UnbilledChargeService;

/**
 * Finance v2 for the first tenant, written through the finance services as the
 * owner: a catalog, plans of every pricing method, a subscription with financial
 * terms, unbilled charges, invoices (draft, issued, void), receipts (a partial
 * allocation and an uncollected cheque) and a voided expense.
 */
class FinanceV2Step implements DemoSeedStep
{
    private const PRICING_METHODS = [
        ['MONTHLY', PricingMethod::Monthly, 40, []],
        ['WEEKLY', PricingMethod::Weekly, 12, []],
        ['DAILY', PricingMethod::Daily, 3, []],
        ['HOURLY', PricingMethod::Hourly, 2, ['overage_price' => 3, 'included_quantity' => 20]],
        ['PERGB', PricingMethod::PerGb, 1, ['overage_price' => 2, 'included_quantity' => 50]],
        ['BUNDLE', PricingMethod::Bundle, 25, ['overage_price' => 3, 'included_quantity' => 30, 'validity_days' => 30]],
    ];

    public function __construct(
        private readonly CatalogCategoryService $categories,
        private readonly CatalogItemService $items,
        private readonly PlanService $plans,
        private readonly UnbilledChargeService $charges,
        private readonly InvoiceLifecycleService $invoices,
        private readonly ReceiptService $receipts,
        private readonly ExpenseService $expenses,
    ) {}

    public function seed(DemoContext $context): void
    {
        $workspace = $context->workspace('ws1');
        $owner = $context->user('owner1');

        $item = $this->seedCatalog($workspace, $owner);
        $plan = $this->seedPlans($workspace, $owner);
        $this->seedFinancialTerms($context, $plan);
        $this->seedInvoicesAndReceipts($workspace, $owner, $context, $item);
        $this->seedVoidedExpense($workspace, $owner);
    }

    private function seedCatalog(Workspace $workspace, User $owner): CatalogItem
    {
        $drinks = $this->category($workspace, $owner, 'DEMO-DRINKS', 'المشروبات', 'Drinks');
        $services = $this->category($workspace, $owner, 'DEMO-SERVICES', 'الخدمات', 'Services');
        $this->category($workspace, $owner, 'DEMO-OLD', 'قديم', 'Old');
        $this->categories->archive(CatalogCategory::query()->withoutWorkspaceScope()->where('workspace_id', $workspace->id)->where('code', 'DEMO-OLD')->firstOrFail(), $owner);

        $this->item($workspace, $owner, $drinks, 'DEMO-COFFEE', 'قهوة مختصة', 'Specialty coffee', CatalogUnit::Piece, 6);
        $this->item($workspace, $owner, $services, 'DEMO-HOUR', 'ساعة إضافية', 'Extra hour', CatalogUnit::Hour, 10);

        return $this->item($workspace, $owner, $services, 'DEMO-PRINT', 'طباعة', 'Printing', CatalogUnit::Piece, 1);
    }

    private function category(Workspace $workspace, User $owner, string $code, string $ar, string $en): CatalogCategory
    {
        return $this->categories->create($workspace, [
            'kind' => CatalogKind::Sales->value, 'code' => $code, 'name_ar' => $ar, 'name_en' => $en,
        ], $owner);
    }

    private function item(Workspace $workspace, User $owner, CatalogCategory $category, string $code, string $ar, string $en, CatalogUnit $unit, float $price): CatalogItem
    {
        return $this->items->create($workspace, [
            'catalog_category_id' => $category->id, 'code' => $code, 'name_ar' => $ar, 'name_en' => $en,
            'unit' => $unit->value, 'price' => $price,
        ], $owner);
    }

    /** One plan per pricing method; the first is returned for the subscription terms. */
    private function seedPlans(Workspace $workspace, User $owner): Plan
    {
        $first = null;

        foreach (self::PRICING_METHODS as [$code, $method, $price, $extra]) {
            $plan = $this->plans->create($workspace, [
                'code' => 'DEMO-'.$code, 'name_ar' => 'خطة '.$code, 'name_en' => 'Plan '.$code,
                'pricing_method' => $method->value, 'price' => $price, ...$extra,
            ], $owner);
            $first ??= $plan;
        }

        return $first;
    }

    private function seedFinancialTerms(DemoContext $context, Plan $plan): void
    {
        Subscription::query()->where('member_id', $context->user('freelancer1')->id)->update([
            'plan_id' => $plan->id,
            'billing_mode' => BillingMode::Postpaid->value,
            'grace_days' => 3,
            'auto_renew' => true,
        ]);
    }

    private function seedInvoicesAndReceipts(Workspace $workspace, User $owner, DemoContext $context, CatalogItem $item): void
    {
        $first = $context->user('freelancer1');
        $second = $context->user('freelancer2');

        $charge = $this->charge($workspace, $owner, $first, 'مطبوعات الشهر', 2, 15);
        $this->charge($workspace, $owner, $first, 'جلسة تصوير', 1, 50);
        $this->charges->void($this->charge($workspace, $owner, $second, 'رسوم مُلغاة', 1, 5), $owner);

        $partial = $this->issue($workspace, $owner, $first, [['unbilled_charge_id' => $charge->id], $this->line('خدمة إضافية', 1, 20, $item)]);
        $cheque = $this->issue($workspace, $owner, $first, [$this->line('اشتراك حجز قاعة', 1, 80, $item)]);
        $voided = $this->issue($workspace, $owner, $second, [$this->line('خدمة ملغاة', 1, 40, $item)]);
        $this->invoices->void($voided, 'Issued by mistake in the demo data.', $owner);
        $this->invoices->createDraft($workspace, $this->draft($second, [$this->line('مسودة', 1, 25, $item)]), $owner);

        // 60 received, 30 allocated: a partial allocation with 30 still free on the receipt.
        $this->receipt($workspace, $owner, $first, PaymentMethod::Cash, 60, [['invoice_id' => $partial->id, 'amount' => 30]]);
        // A cheque is not collected until it clears, so its invoice stays unpaid.
        $this->receipt($workspace, $owner, $first, PaymentMethod::Cheque, 80, [['invoice_id' => $cheque->id, 'amount' => 80]]);
    }

    private function charge(Workspace $workspace, User $owner, User $customer, string $description, int $qty, int $price): UnbilledCharge
    {
        return $this->charges->record($workspace, [
            'customer_id' => $customer->id, 'description' => $description, 'qty' => $qty.'.00',
            'unit_price' => $price.'.00', 'source' => UnbilledChargeSource::Manual->value,
        ], $owner);
    }

    /** @return array<string, mixed> */
    private function line(string $description, int $qty, int $price, CatalogItem $item): array
    {
        return ['description' => $description, 'qty' => $qty.'.00', 'unit_price' => $price.'.00', 'catalog_item_id' => $item->id];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function draft(User $customer, array $lines): array
    {
        return ['customer_id' => $customer->id, 'due_date' => now()->addWeeks(2)->toDateString(), 'lines' => $lines];
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function issue(Workspace $workspace, User $owner, User $customer, array $lines): Invoice
    {
        return $this->invoices->issue($this->invoices->createDraft($workspace, $this->draft($customer, $lines), $owner), $owner);
    }

    /** @param  list<array{invoice_id: string, amount: int}>  $allocations */
    private function receipt(Workspace $workspace, User $owner, User $customer, PaymentMethod $method, int $amount, array $allocations): Receipt
    {
        return $this->receipts->create($workspace, [
            'customer_id' => $customer->id, 'method' => $method->value, 'amount' => $amount.'.00',
            'allocations' => array_map(static fn (array $row): array => ['invoice_id' => $row['invoice_id'], 'amount' => $row['amount'].'.00'], $allocations),
        ], $owner);
    }

    private function seedVoidedExpense(Workspace $workspace, User $owner): void
    {
        $expense = $this->expenses->create($workspace, [
            'title' => 'مصروف أُدخل بالخطأ', 'category' => ExpenseCategory::Other->value, 'amount' => '75.00',
            'spent_on' => now()->subDay()->toDateString(),
        ], $owner);

        $this->expenses->void($workspace, $expense, 'Entered twice in the demo data.', $owner);
    }
}
