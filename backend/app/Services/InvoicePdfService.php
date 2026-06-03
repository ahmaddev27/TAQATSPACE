<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoicePdfService
{
    /**
     * Stream the invoice PDF as an attachment, generating + caching it on the
     * default disk on first request and reusing it afterwards.
     */
    public function streamDownload(Invoice $invoice): StreamedResponse
    {
        $path = $this->ensureGenerated($invoice);
        $disk = Storage::disk();

        return $disk->download($path, $this->fileName($invoice));
    }

    /**
     * Generate (if needed) and return the stored disk path for the invoice PDF.
     */
    public function ensureGenerated(Invoice $invoice): string
    {
        $path = "invoices/{$invoice->id}.pdf";
        $disk = Storage::disk();

        if ($invoice->invoice_pdf_path !== null && $disk->exists($invoice->invoice_pdf_path)) {
            return $invoice->invoice_pdf_path;
        }

        $invoice->loadMissing('subscription.member', 'subscription.seat', 'subscription.workspace');

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4')
            ->setOption('defaultFont', 'DejaVu Sans');

        $disk->put($path, $pdf->output());

        $invoice->forceFill(['invoice_pdf_path' => $path])->save();

        return $path;
    }

    private function fileName(Invoice $invoice): string
    {
        return $invoice->invoice_number.'.pdf';
    }
}
