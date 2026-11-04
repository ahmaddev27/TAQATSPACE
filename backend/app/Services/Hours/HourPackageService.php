<?php

declare(strict_types=1);

namespace App\Services\Hours;

use App\Enums\HourPurchaseStatus;
use App\Exceptions\HourPackageException;
use App\Models\HourPackage;
use App\Models\HourPackagePurchase;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Platform hour-package catalog. Purchases freeze hours and price when
 * requested, so catalog edits and archiving never touch existing purchases.
 */
class HourPackageService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return Collection<int, HourPackage> */
    public function all(): Collection
    {
        return HourPackage::query()->orderBy('sort_order')->orderBy('hours')->get();
    }

    /**
     * Purchase queue, newest first, with buyer and package loaded.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, HourPackagePurchase>
     */
    public function paginatePurchases(array $filters): LengthAwarePaginator
    {
        $status = HourPurchaseStatus::tryFrom((string) ($filters['status'] ?? ''));
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return HourPackagePurchase::query()
            ->with(['user:id,name,email', 'package'])
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return array<string, int> purchase counts keyed by status */
    public function purchaseStats(): array
    {
        $counts = HourPackagePurchase::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(HourPurchaseStatus::values())
            ->mapWithKeys(fn (string $status): array => [$status => (int) ($counts[$status] ?? 0)])
            ->all();
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): HourPackage
    {
        $package = HourPackage::query()->create($data);

        $this->audit->log('hours.package.created', $package, changes: $package->getAttributes());

        return $package;
    }

    /** @param  array<string, mixed>  $data */
    public function update(HourPackage $package, array $data): HourPackage
    {
        $package->fill($data);
        $changes = $package->getDirty();
        $package->save();

        $this->audit->log('hours.package.updated', $package, changes: $changes);

        return $package;
    }

    public function archive(HourPackage $package): HourPackage
    {
        if ($package->is_active) {
            $package->forceFill(['is_active' => false])->save();
            $this->audit->log('hours.package.archived', $package);
        }

        return $package;
    }

    /**
     * Open a purchase request freezing the package's current hours and price.
     *
     * @throws HourPackageException when the package is archived
     */
    public function requestPurchase(User $user, HourPackage $package): HourPackagePurchase
    {
        if (! $package->is_active) {
            throw HourPackageException::packageUnavailable();
        }

        return HourPackagePurchase::query()->create([
            'user_id' => $user->getKey(),
            'hour_package_id' => $package->getKey(),
            'hours' => $package->hours,
            'price' => $package->price,
            'currency' => $package->currency,
            'status' => HourPurchaseStatus::Requested->value,
        ]);
    }
}
