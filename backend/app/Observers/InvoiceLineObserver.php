<?php

declare(strict_types=1);

namespace App\Observers;

use App\Exceptions\InvoiceLifecycleException;
use App\Models\InvoiceLine;

/** Lines belong to their invoice's lifecycle: only a draft's lines may change. */
class InvoiceLineObserver
{
    public function creating(InvoiceLine $line): void
    {
        $this->guard($line);
    }

    public function updating(InvoiceLine $line): void
    {
        $this->guard($line);
    }

    public function deleting(InvoiceLine $line): void
    {
        $this->guard($line);
    }

    private function guard(InvoiceLine $line): void
    {
        if (! $line->invoice()->firstOrFail()->isDraft()) {
            throw InvoiceLifecycleException::immutable();
        }
    }
}
