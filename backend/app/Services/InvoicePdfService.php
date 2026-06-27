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
     * Bump whenever the invoice template/layout changes. The cached PDF path is
     * namespaced by this version, so a bump transparently invalidates every
     * previously-cached PDF — old invoices regenerate with the new template
     * instead of serving a stale render.
     */
    private const TEMPLATE_VERSION = 4;

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
        $prefix = 'invoices/v'.self::TEMPLATE_VERSION.'/';

        // Fingerprint the payment-relevant state so a paid (or partially paid)
        // invoice regenerates instead of serving a stale "unpaid" cached PDF.
        $fingerprint = substr(md5(implode('|', [
            $invoice->status->value,
            (string) $invoice->amount,
            (string) $invoice->amount_paid,
            (string) ($invoice->paid_at?->getTimestamp() ?? ''),
        ])), 0, 10);

        $path = $prefix."{$invoice->id}-{$fingerprint}.pdf";
        $disk = Storage::disk();

        // Reuse only the cache for this exact template version + payment state.
        if (
            $invoice->invoice_pdf_path === $path
            && $disk->exists($path)
        ) {
            return $path;
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
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $this->tempDir(),
            // Keep Arabic letter-shaping/bidi, but DO NOT let mPDF auto-substitute
            // its bundled Arabic fonts (XB Riyaz) for our CSS-declared Cairo font.
            'autoScriptToLang' => false,
            'autoArabic' => true,
            'autoLangToFont' => false,
            'margin_top' => 14,
            'margin_bottom' => 14,
            'margin_left' => 14,
            'margin_right' => 14,

            // Register the brand font (Cairo) so the Arabic-first invoice renders
            // with a modern, properly-shaped typeface instead of mPDF's default.
            'fontDir' => array_merge($defaultConfig['fontDir'], [$this->fontDir()]),
            'fontdata' => $defaultFontConfig['fontdata'] + [
                'cairo' => [
                    'R' => 'Cairo-Regular.ttf',
                    'B' => 'Cairo-Bold.ttf',
                    // Cairo is a modern font with no legacy presentation-form
                    // glyphs, so mPDF's autoArabic shaper cannot join it. Enable
                    // OpenType layout (GSUB/GPOS) so Arabic letters connect.
                    'useOTL' => 0xFF,
                    'useKashida' => 0,
                ],
            ],
            'default_font' => 'cairo',
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

    /**
     * Directory holding the bundled Cairo TTF files registered with mPDF.
     */
    private function fontDir(): string
    {
        return resource_path('fonts');
    }

    private function fileName(Invoice $invoice): string
    {
        return $invoice->invoice_number.'.pdf';
    }
}
