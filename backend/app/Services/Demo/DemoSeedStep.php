<?php

declare(strict_types=1);

namespace App\Services\Demo;

/** One area of demo data (accounts, finance, ...). Register it in config/demo.php. */
interface DemoSeedStep
{
    public function seed(DemoContext $context): void;
}
