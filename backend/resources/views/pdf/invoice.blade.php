@php
    /** @var \App\Models\Invoice $invoice */
    $subscription = $invoice->subscription;
    $member = $subscription?->member;
    $workspace = $subscription?->workspace;
    $seat = $subscription?->seat;

    $status = $invoice->status;
    $statusValue = $status->value;
    $isPaid = $status === \App\Enums\InvoiceStatus::Paid;

    $statusLabels = [
        'paid' => 'مدفوعة',
        'partially_paid' => 'مدفوعة جزئياً',
        'overdue' => 'متأخرة',
        'cancelled' => 'ملغاة',
        'under_review' => 'قيد المراجعة',
        'payment_rejected' => 'وصل مرفوض',
        'pending' => 'غير مدفوعة',
    ];

    $isPartiallyPaid = $status === \App\Enums\InvoiceStatus::PartiallyPaid;
    $amountPaid = (float) ($invoice->amount_paid ?? 0);
    $remaining = max(0, (float) $invoice->amount - $amountPaid);

    // When a receipt has been uploaded but the stored status is still pending or
    // overdue (e.g. a lag before the status transition completed), the PDF should
    // display the truthful "under review" label instead of the stale one.
    $displayStatusValue = $statusValue;
    if (
        $invoice->receipt_path !== null
        && in_array($statusValue, ['pending', 'overdue'], true)
    ) {
        $displayStatusValue = 'under_review';
    }

    $statusLabel = $statusLabels[$displayStatusValue] ?? $statusLabels['pending'];

    $startDate = $subscription?->start_date?->format('Y-m-d') ?? '—';
    $endDate = $subscription?->end_date?->format('Y-m-d') ?? '—';
    $period = $startDate . '  –  ' . $endDate;

    $lineAmount = $subscription?->monthly_price ?? $invoice->amount;

    // Internet package add-ons assigned to this member in the workspace, priced
    // above zero — listed as their own invoice line(s) under the subscription.
    $packageLines = ($member && $workspace)
        ? $member->internetPackages()
            ->where('internet_packages.workspace_id', $workspace->id)
            ->where('internet_packages.price', '>', 0)
            ->get(['internet_packages.id', 'internet_packages.name', 'internet_packages.price', 'internet_packages.speed_mbps'])
        : collect();
    $packagesTotal = (float) $packageLines->sum('price');
    $subtotal = (float) $lineAmount + $packagesTotal;

    // Cairo lacks the ₪ glyph (U+20AA), so the shekel sign is rendered in DejaVu
    // Sans (bundled with mPDF, has the glyph) — shows ₪ correctly, not tofu.
    $money = static fn ($value): string =>
        '<span class="num">' . number_format((float) $value, 2) . '</span> <span class="shekel">₪</span>';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 16mm 15mm; }

        body {
            font-family: 'cairo', sans-serif;
            direction: rtl;
            color: #0E1726;
            font-size: 12px;
            line-height: 1.95;
            margin: 0;
            padding: 0;
        }

        /* LTR-embed runs of numbers / Latin so they read correctly inside RTL. */
        .num {
            direction: ltr;
            unicode-bidi: embed;
            font-size: 0.95em;
        }

        .muted { color: #667085; }

        /* English sub-line under an Arabic label: small, muted, uppercased.
           Placed on its own line with an explicit <br> (mPDF does not reliably
           honour display:block on an inline span). */
        .en {
            font-size: 8.5px;
            color: #98A2B3;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            line-height: 1.4;
        }

        /* ---------- Header ---------- */
        .header { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        .header td { vertical-align: top; }

        /* Render the shekel sign in a font that has the glyph (Cairo lacks it). */
        .shekel { font-family: dejavusans, sans-serif; }

        .wordmark {
            font-size: 30px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #1F82C7;
            line-height: 1.45;
        }
        .wordmark .dot { color: #F6A91B; }
        .tagline {
            font-size: 10.5px;
            color: #667085;
            margin-top: 6px;
            line-height: 1.5;
        }

        .doc-title {
            font-size: 22px;
            font-weight: bold;
            color: #0E1726;
            line-height: 1.6;
            margin-bottom: 4px;
        }
        .doc-number {
            margin-top: 8px;
            font-size: 11.5px;
            color: #667085;
            line-height: 1.6;
        }

        .rule {
            height: 3px;
            background: #1F82C7;
            line-height: 0;
            font-size: 0;
            margin: 14px 0 26px;
        }

        /* ---------- Status badge ---------- */
        /* Status pill — a 1-cell table so mPDF reserves real vertical space and
           never overlaps the invoice number above it. */
        .status-tbl { width: auto; margin-top: 16px; }
        .status-pill {
            padding: 9px 30px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            white-space: nowrap;
            line-height: 1.4;
        }
        .badge-paid { background: #E6F6EC; color: #1B8A4B; }
        .badge-partially_paid { background: #FEF3D6; color: #B5790B; }
        .badge-pending { background: #EEF1F5; color: #667085; }
        .badge-under_review { background: #FEF3D6; color: #B5790B; }
        .badge-payment_rejected { background: #FDE7E7; color: #C0392B; }
        .badge-overdue { background: #FDE7E7; color: #C0392B; }
        .badge-cancelled { background: #EEF1F5; color: #667085; }

        /* ---------- Party cards ----------
           One bordered, rounded strip with two equal cells + a divider — the
           SAME td-styled pattern as the .meta strip below, which mPDF renders
           reliably. A background+border inner <div> inside each <td> collapsed
           in mPDF (no box drawn, and the stacked lines overlapped), so the box
           now lives on the table + cells, never on an inner div. */
        .parties {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #E4E9F0;
            border-radius: 10px;
            background: #F7FAFC;
            margin: 0 0 28px;
        }
        .parties td {
            width: 50%;
            padding: 18px 20px;
            vertical-align: top;
        }
        .parties td + td { border-right: 1px solid #E4E9F0; }
        .party-label {
            font-size: 11px;
            font-weight: bold;
            color: #1F82C7;
            line-height: 1.6;
            margin-bottom: 4px;
        }
        .party-label-en {
            display: block;
            font-size: 8.5px;
            font-weight: normal;
            color: #98A2B3;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        .party-name { font-size: 15px; font-weight: bold; color: #0E1726; line-height: 1.7; margin-bottom: 6px; }
        .party-line { color: #667085; font-size: 11px; line-height: 1.9; }

        /* ---------- Meta strip ---------- */
        .meta {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #E4E9F0;
            border-radius: 10px;
            margin-bottom: 28px;
        }
        .meta td {
            padding: 14px 18px;
            width: 33.33%;
            vertical-align: top;
        }
        .meta td + td { border-right: 1px solid #E4E9F0; }
        .meta .meta-label { font-size: 10.5px; color: #667085; font-weight: bold; line-height: 1.6; }
        .meta .meta-label-en { font-size: 8.5px; color: #98A2B3; font-weight: normal; letter-spacing: 0.6px; text-transform: uppercase; line-height: 1.6; display: block; }
        .meta .meta-value { font-size: 13px; font-weight: bold; color: #0E1726; margin-top: 8px; }

        /* ---------- Line items ---------- */
        .items { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .items thead th {
            background: #1F82C7;
            color: #ffffff;
            font-weight: bold;
            font-size: 11.5px;
            padding: 13px 16px;
            text-align: right;
            line-height: 1.4;
        }
        .items thead th:last-child { text-align: left; }
        .items thead th:first-child { border-top-right-radius: 8px; }
        .items thead th:last-child { border-top-left-radius: 8px; }
        .items tbody td {
            border-bottom: 1px solid #E4E9F0;
            padding: 18px 16px;
            text-align: right;
            vertical-align: top;
            line-height: 1.9;
        }
        .items tbody td:last-child { text-align: left; font-weight: bold; }
        .items .desc-title { font-weight: bold; color: #0E1726; font-size: 12.5px; line-height: 1.7; }
        .items .desc-sub { color: #667085; font-size: 11px; margin-top: 7px; line-height: 1.7; }

        /* ---------- Totals ---------- */
        .totals { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .totals .spacer { width: 52%; }
        .totals td { padding: 8px 16px; }
        .totals .t-label { color: #667085; text-align: right; font-size: 12px; }
        .totals .t-value { text-align: left; color: #0E1726; font-weight: bold; font-size: 12.5px; }
        .totals .grand td { border-top: 2px solid #1F82C7; padding-top: 16px; }
        .totals .grand .t-label { font-size: 14px; font-weight: bold; color: #0E1726; }
        .totals .grand .t-value { font-size: 20px; font-weight: bold; color: #1F82C7; }

        /* ---------- Footer ---------- */
        .footer { margin-top: 40px; border-top: 1px solid #E4E9F0; padding-top: 16px; }
        .footer-rule {
            height: 3px;
            width: 72px;
            background: #F6A91B;
            margin-bottom: 14px;
            line-height: 0;
            font-size: 0;
        }
        .footer-dates { width: 100%; border-collapse: collapse; }
        .footer-dates td { color: #667085; font-size: 11px; line-height: 1.7; }
        .footer-thanks {
            margin-top: 16px;
            text-align: center;
            color: #667085;
            font-size: 11px;
        }
    </style>
</head>
<body>

    {{-- Header: brand wordmark vs. document title + status --}}
    <table class="header">
        <tr>
            <td style="text-align: right;">
                <div class="wordmark">TAQAT<span class="dot">.</span></div>
                <div class="tagline">طاقة لمساحات العمل المشتركة</div>
            </td>
            <td style="text-align: left;">
                <div class="doc-title">فاتورة</div>
                <div class="doc-number">
                    رقم <span class="num">{{ $invoice->invoice_number }}</span>
                </div>
                {{-- Status as a 1-cell table: mPDF renders cell backgrounds
                     reliably (inline-block pills overlapped the line above). --}}
                <table class="status-tbl" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="status-pill badge-{{ $displayStatusValue }}">{{ $statusLabel }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    {{-- Issuer vs. recipient --}}
    <table class="parties">
        <tr>
            <td>
                <div class="party-label">المُصدِر</div>
                <div class="party-label-en">From</div>
                <div class="party-name">{{ $workspace?->name ?? 'TAQAT' }}</div>
                @if ($workspace?->address)
                    <div class="party-line">{{ $workspace->address }}</div>
                @endif
                @if ($workspace?->city)
                    <div class="party-line">{{ $workspace->city }}</div>
                @endif
                @if ($workspace?->phone)
                    <div class="party-line"><span class="num">{{ $workspace->phone }}</span></div>
                @endif
            </td>
            <td>
                <div class="party-label">العميل</div>
                <div class="party-label-en">Bill to</div>
                <div class="party-name">{{ $member?->name ?? '—' }}</div>
                @if ($member?->email)
                    <div class="party-line"><span class="num">{{ $member->email }}</span></div>
                @endif
                @if ($member?->phone)
                    <div class="party-line"><span class="num">{{ $member->phone }}</span></div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Meta strip: number + key dates --}}
    <table class="meta">
        <tr>
            <td>
                <div class="meta-label">رقم الفاتورة</div>
                <div class="meta-label-en">Invoice no.</div>
                <div class="meta-value"><span class="num">{{ $invoice->invoice_number }}</span></div>
            </td>
            <td>
                <div class="meta-label">تاريخ الإصدار</div>
                <div class="meta-label-en">Issue date</div>
                <div class="meta-value"><span class="num">{{ $invoice->created_at?->format('Y-m-d') ?? '—' }}</span></div>
            </td>
            <td>
                <div class="meta-label">تاريخ الاستحقاق</div>
                <div class="meta-label-en">Due date</div>
                <div class="meta-value"><span class="num">{{ $invoice->due_date?->format('Y-m-d') ?? '—' }}</span></div>
            </td>
        </tr>
    </table>

    {{-- Line items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 46%;">الوصف<div class="en">Description</div></th>
                <th style="width: 30%;">الفترة<div class="en">Period</div></th>
                <th style="width: 24%;">المبلغ<div class="en">Amount</div></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="desc-title">اشتراك شهري</div>
                    <div class="desc-sub">Monthly subscription</div>
                    @if ($seat?->seat_number)
                        <div class="desc-sub">المقعد: <span class="num">{{ $seat->seat_number }}</span></div>
                    @endif
                </td>
                <td><span class="num">{{ $period }}</span></td>
                <td>{!! $money($lineAmount) !!}</td>
            </tr>
            @foreach ($packageLines as $pkg)
                <tr>
                    <td>
                        <div class="desc-title">باقة الإنترنت: {{ $pkg->name }}</div>
                        <div class="desc-sub">Internet package</div>
                        @if ($pkg->speed_mbps)
                            <div class="desc-sub"><span class="num">{{ $pkg->speed_mbps }}</span> Mbps</div>
                        @endif
                    </td>
                    <td><span class="num">{{ $period }}</span></td>
                    <td>{!! $money($pkg->price) !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <table class="totals">
        <tr>
            <td class="spacer"></td>
            <td class="t-label">المجموع الفرعي</td>
            <td class="t-value">{!! $money($subtotal) !!}</td>
        </tr>
        <tr class="grand">
            <td class="spacer"></td>
            <td class="t-label">الإجمالي المستحق</td>
            <td class="t-value">{!! $money($invoice->amount) !!}</td>
        </tr>
        @if ($amountPaid > 0 && ($isPartiallyPaid || $remaining > 0.001))
            <tr>
                <td class="spacer"></td>
                <td class="t-label">المدفوع</td>
                <td class="t-value">{!! $money($amountPaid) !!}</td>
            </tr>
            <tr>
                <td class="spacer"></td>
                <td class="t-label" style="color:#B54708;">المتبقّي</td>
                <td class="t-value" style="color:#B54708;">{!! $money($remaining) !!}</td>
            </tr>
        @endif
    </table>

    {{-- Payment details: surfaced once a receipt is attached or the invoice is
         settled, so the PDF shows the real status + when/how it was paid. --}}
    @if ($isPaid || $invoice->receipt_path)
        <table class="meta" style="margin-top: 30px;">
            <tr>
                <td>
                    <div class="meta-label">حالة الدفع</div>
                    <div class="meta-label-en">Payment status</div>
                    <div class="meta-value">{{ $statusLabel }}</div>
                </td>
                <td>
                    <div class="meta-label">تاريخ الدفع</div>
                    <div class="meta-label-en">Paid on</div>
                    <div class="meta-value"><span class="num">{{ $invoice->paid_at?->format('Y-m-d') ?? '—' }}</span></div>
                </td>
                <td>
                    <div class="meta-label">إيصال الدفع</div>
                    <div class="meta-label-en">Receipt</div>
                    <div class="meta-value">{{ $invoice->receipt_path ? 'مُرفق' : '—' }}</div>
                </td>
            </tr>
        </table>
    @endif

    {{-- Footer --}}
    <div class="footer">
        <div class="footer-rule"></div>
        <table class="footer-dates">
            <tr>
                <td style="text-align: right;">
                    تاريخ الإصدار: <span class="num">{{ $invoice->created_at?->format('Y-m-d') ?? '—' }}</span>
                </td>
                <td style="text-align: left;">
                    @if ($isPaid && $invoice->paid_at)
                        تاريخ الدفع: <span class="num">{{ $invoice->paid_at->format('Y-m-d') }}</span>
                    @else
                        تاريخ الاستحقاق: <span class="num">{{ $invoice->due_date?->format('Y-m-d') ?? '—' }}</span>
                    @endif
                </td>
            </tr>
        </table>
        <div class="footer-thanks">
            شكراً لاشتراكك معنا. نتمنى لك تجربة عمل موفّقة.
        </div>
    </div>

</body>
</html>
