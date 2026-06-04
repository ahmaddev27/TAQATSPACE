<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
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

        $disk->put($path, $this->render($invoice));

        $invoice->forceFill(['invoice_pdf_path' => $path])->save();

        return $path;
    }

    /**
     * Render the invoice as a PDF binary string using mPDF, which performs
     * native Arabic shaping (letter joining) and RTL bidi — something DomPDF
     * cannot do — so the Arabic-first template renders correctly.
     */
    private function render(Invoice $invoice): string
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $this->tempDir(),
            'autoScriptToLang' => true,
            'autoArabic' => true,
            'autoLangToFont' => true,
            'margin_top' => 14,
            'margin_bottom' => 14,
            'margin_left' => 14,
            'margin_right' => 14,
        ]);

        $mpdf->SetDirectionality('rtl');

        $html = View::make('pdf.invoice', ['invoice' => $invoice])->render();

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * mPDF requires a writable temp directory; ensure it exists before use.
     */
    private function tempDir(): string
    {
        $dir = storage_path('app/mpdf');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function fileName(Invoice $invoice): string
    {
        return $invoice->invoice_number.'.pdf';
    }
}
