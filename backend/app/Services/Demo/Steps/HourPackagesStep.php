<?php

declare(strict_types=1);

namespace App\Services\Demo\Steps;

use App\Models\HourPackage;
use App\Services\Demo\DemoContext;
use App\Services\Demo\DemoSeedStep;
use App\Services\Hours\HourPackageService;

/**
 * Platform hour packages and one purchase waiting for activation. Packages
 * belong to the platform, not to a workspace, so the code prefix is what marks
 * them as demo data (see DemoCleaner).
 */
class HourPackagesStep implements DemoSeedStep
{
    public const CODE_PREFIX = 'DEMO-';

    /** @var list<array{0: string, 1: string, 2: string, 3: int, 4: int}> code, Arabic name, English name, hours, price */
    private const PACKAGES = [
        ['DEMO-HOURS-10', '10 ساعات', '10 hours', 10, 20],
        ['DEMO-HOURS-50', '50 ساعة', '50 hours', 50, 80],
    ];

    public function __construct(private readonly HourPackageService $packages) {}

    public function seed(DemoContext $context): void
    {
        $created = array_map(fn (array $row): HourPackage => $this->packages->create([
            'code' => $row[0], 'name_ar' => $row[1], 'name_en' => $row[2],
            'hours' => $row[3], 'price' => $row[4], 'currency' => 'USD', 'validity_days' => 90,
        ]), self::PACKAGES);

        $this->packages->requestPurchase($context->user('freelancer1'), $created[0]);
    }
}
