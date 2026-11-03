<?php

declare(strict_types=1);

namespace App\Services\Hours;

use App\Enums\HourLedgerReason;
use App\Enums\HourPurchaseStatus;
use App\Exceptions\HourPackageException;
use App\Models\HourLedgerEntry;
use App\Models\HourPackagePurchase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Hour balances. The balance is always the sum of the append-only ledger,
 * never a stored counter. There is no payment gateway: activation records a
 * payment made outside the platform.
 */
class HourBalanceService
{
    private const MINUTES_PER_HOUR = 60;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly FileUploadService $uploads,
    ) {}

    /** Current balance in minutes. */
    public function balance(User $user): int
    {
        return (int) HourLedgerEntry::query()->where('user_id', $user->getKey())->sum('minutes');
    }

    /**
     * Record the offline payment and credit the frozen hours exactly once. The
     * purchase row is locked and re-read so a repeated request finds it no
     * longer "requested" and is rejected instead of crediting twice.
     *
     * @param  array{payment_method: string, paid_at?: ?Carbon}  $payment
     *
     * @throws HourPackageException when the purchase is not awaiting activation
     */
    public function activate(
        HourPackagePurchase $purchase,
        array $payment,
        ?UploadedFile $receipt,
        User $actor,
    ): HourPackagePurchase {
        return DB::transaction(function () use ($purchase, $payment, $receipt, $actor): HourPackagePurchase {
            $locked = $this->lockRequested($purchase);
            $now = Carbon::now();

            $locked->forceFill([
                'status' => HourPurchaseStatus::Active->value,
                'activated_at' => $now,
                'activated_by' => $actor->getKey(),
                'expires_at' => $this->expiryFor($locked, $now),
                'payment_method' => $payment['payment_method'],
                'paid_at' => $payment['paid_at'] ?? $now,
                'receipt_path' => $receipt !== null ? $this->storeReceipt($locked, $receipt) : null,
            ])->save();

            $this->append($locked->user_id, $locked->hours * self::MINUTES_PER_HOUR, HourLedgerReason::Credit, [
                'hour_package_purchase_id' => $locked->getKey(),
                'created_by' => $actor->getKey(),
            ]);
            $this->audit->log('hours.purchase.activated', $locked, actor: $actor, changes: [
                'hours' => $locked->hours,
                'payment_method' => $payment['payment_method'],
            ]);

            return $locked->load(['user:id,name,email', 'package']);
        });
    }

    /** @throws HourPackageException when the purchase is not awaiting activation */
    public function cancel(HourPackagePurchase $purchase, User $actor): HourPackagePurchase
    {
        return DB::transaction(function () use ($purchase, $actor): HourPackagePurchase {
            $locked = $this->lockRequested($purchase);
            $locked->forceFill(['status' => HourPurchaseStatus::Cancelled->value])->save();
            $this->audit->log('hours.purchase.cancelled', $locked, actor: $actor);

            return $locked->load(['user:id,name,email', 'package']);
        });
    }

    /**
     * Manual correction as a signed ledger entry; the reason is mandatory.
     *
     * @throws HourPackageException when the balance would go negative
     */
    public function adjust(User $user, int $minutes, string $reason, User $actor): HourLedgerEntry
    {
        return DB::transaction(function () use ($user, $minutes, $reason, $actor): HourLedgerEntry {
            // Lock the user row so concurrent adjustments see a consistent balance.
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if ($this->balance($user) + $minutes < 0) {
                throw HourPackageException::negativeBalance();
            }

            $entry = $this->append($user->getKey(), $minutes, HourLedgerReason::Adjustment, [
                'note' => $reason,
                'created_by' => $actor->getKey(),
            ]);
            $this->audit->log('hours.adjusted', $user, reason: $reason, actor: $actor, changes: ['minutes' => $minutes]);

            return $entry;
        });
    }

    private function lockRequested(HourPackagePurchase $purchase): HourPackagePurchase
    {
        $locked = HourPackagePurchase::query()->whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->status !== HourPurchaseStatus::Requested) {
            throw HourPackageException::purchaseNotRequested();
        }

        return $locked;
    }

    private function expiryFor(HourPackagePurchase $purchase, Carbon $from): ?Carbon
    {
        $days = $purchase->package?->validity_days;

        return $days === null ? null : $from->copy()->addDays($days);
    }

    private function storeReceipt(HourPackagePurchase $purchase, UploadedFile $receipt): string
    {
        return $this->uploads->upload(
            $receipt,
            'hour-receipts/'.$purchase->getKey(),
            (string) config('filesystems.media', 'public'),
            'public',
        );
    }

    /** @param  array<string, mixed>  $extra */
    private function append(string $userId, int $minutes, HourLedgerReason $reason, array $extra = []): HourLedgerEntry
    {
        return HourLedgerEntry::query()->create([
            'user_id' => $userId,
            'minutes' => $minutes,
            'reason' => $reason->value,
            ...$extra,
        ]);
    }
}
