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
        'paid' => 'مدفوع / PAID',
        'overdue' => 'متأخر / OVERDUE',
        'cancelled' => 'ملغى / CANCELLED',
        'pending' => 'قيد الانتظار / PENDING',
    ];
    $statusLabel = $statusLabels[$statusValue] ?? $statusLabels['pending'];

    $startDate = $subscription?->start_date?->format('Y-m-d') ?? '—';
    $endDate = $subscription?->end_date?->format('Y-m-d') ?? '—';
    $period = $startDate . '  —  ' . $endDate;

    $lineAmount = $subscription?->monthly_price ?? $invoice->amount;

    $money = static fn ($value): string => '₪ ' . number_format((float) $value, 2);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm; }

        body {
            font-family: 'DejaVuSansCondensed', sans-serif;
            direction: rtl;
            color: #0E1726;
            font-size: 11.5px;
            line-height: 1.55;
            margin: 0;
            padding: 0;
        }

        .num { direction: ltr; unicode-bidi: embed; }

        /* ---- Header band ---- */
        .header {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 3px solid #1F82C7;
            margin-bottom: 22px;
            padding-bottom: 14px;
        }
        .header td { vertical-align: middle; }

        .wordmark {
            font-size: 30px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #1F82C7;
        }
        .wordmark .dot { color: #F6A91B; }
        .tagline {
            font-size: 11px;
            color: #667085;
            margin-top: 3px;
        }

        .doc-title {
            font-size: 20px;
            font-weight: bold;
            color: #0E1726;
        }
        .doc-number {
            margin-top: 4px;
            font-size: 12px;
            color: #667085;
        }

        /* ---- Status badge ---- */
        .badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 14px;
            font-size: 10.5px;
            font-weight: bold;
        }
        .badge-paid { background: #E6F6EC; color: #1B8A4B; }
        .badge-pending { background: #FEF3D6; color: #B5790B; }
        .badge-overdue { background: #FDE7E7; color: #C0392B; }
        .badge-cancelled { background: #EEF1F5; color: #667085; }

        /* ---- Party block (two columns) ---- */
        .parties { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin-bottom: 24px; }
        .party-cell { width: 50%; vertical-align: top; }
        .party-card {
            background: #F7FAFC;
            border: 1px solid #E4E9F0;
            border-radius: 8px;
            padding: 14px 16px;
        }
        .party-label {
            font-size: 10px;
            font-weight: bold;
            color: #1F82C7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .party-name { font-size: 13px; font-weight: bold; color: #0E1726; }
        .party-line { color: #667085; margin-top: 2px; }

        /* ---- Meta strip ---- */
        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
        }
        .meta td {
            border: 1px solid #E4E9F0;
            padding: 9px 12px;
            width: 33.33%;
        }
        .meta .meta-label { font-size: 9.5px; color: #667085; }
        .meta .meta-value { font-size: 12px; font-weight: bold; color: #0E1726; margin-top: 2px; }

        /* ---- Items table ---- */
        .items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .items thead th {
            background: #1F82C7;
            color: #ffffff;
            font-weight: bold;
            font-size: 11px;
            padding: 10px 12px;
            text-align: right;
        }
        .items thead th:last-child { text-align: left; }
        .items tbody td {
            border-bottom: 1px solid #E4E9F0;
            padding: 11px 12px;
            text-align: right;
            vertical-align: top;
        }
        .items tbody td:last-child { text-align: left; }
        .items .desc-title { font-weight: bold; color: #0E1726; }
        .items .desc-sub { color: #667085; font-size: 10.5px; margin-top: 2px; }

        /* ---- Totals ---- */
        .totals { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .totals .spacer { width: 55%; }
        .totals td {
            padding: 6px 12px;
        }
        .totals .t-label { color: #667085; text-align: right; }
        .totals .t-value { text-align: left; color: #0E1726; font-weight: bold; }
        .totals .grand {
            border-top: 2px solid #1F82C7;
        }
        .totals .grand .t-label {
            font-size: 13px;
            font-weight: bold;
            color: #0E1726;
            padding-top: 12px;
        }
        .totals .grand .t-value {
            font-size: 18px;
            font-weight: bold;
            color: #1F82C7;
            padding-top: 12px;
        }

        /* ---- Footer ---- */
        .footer {
            margin-top: 34px;
            border-top: 1px solid #E4E9F0;
            padding-top: 12px;
        }
        .footer-rule {
            height: 3px;
            width: 64px;
            background: #F6A91B;
            margin-bottom: 10px;
        }
        .footer-dates { width: 100%; border-collapse: collapse; }
        .footer-dates td { color: #667085; font-size: 10.5px; }
        .footer-thanks {
            margin-top: 12px;
            text-align: center;
            color: #667085;
            font-size: 10.5px;
        }
    </style>
</head>
<body>

    {{-- Header band: wordmark vs. document title --}}
    <table class="header">
        <tr>
            <td style="text-align: right;">
                <div class="wordmark">TAQAT<span class="dot">.</span></div>
                <div class="tagline">طاقة لمساحات العمل المشتركة · Coworking Spaces</div>
            </td>
            <td style="text-align: left;">
                <div class="doc-title">فاتورة · Invoice</div>
                <div class="doc-number num">{{ $invoice->invoice_number }}</div>
                <div style="margin-top: 8px;">
                    <span class="badge badge-{{ $statusValue }}">{{ $statusLabel }}</span>
                </div>
            </td>
        </tr>
    </table>

    {{-- Issuer vs. recipient --}}
    <table class="parties">
        <tr>
            <td class="party-cell">
                <div class="party-card">
                    <div class="party-label">من · From</div>
                    <div class="party-name">{{ $workspace?->name ?? 'TAQAT' }}</div>
                    @if ($workspace?->address)
                        <div class="party-line">{{ $workspace->address }}</div>
                    @endif
                    @if ($workspace?->city)
                        <div class="party-line">{{ $workspace->city }}</div>
                    @endif
                    @if ($workspace?->phone)
                        <div class="party-line num">{{ $workspace->phone }}</div>
                    @endif
                </div>
            </td>
            <td class="party-cell">
                <div class="party-card">
                    <div class="party-label">إلى · Bill to</div>
                    <div class="party-name">{{ $member?->name ?? '—' }}</div>
                    @if ($member?->email)
                        <div class="party-line num">{{ $member->email }}</div>
                    @endif
                    @if ($member?->phone)
                        <div class="party-line num">{{ $member->phone }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Meta strip: numbers + key dates --}}
    <table class="meta">
        <tr>
            <td>
                <div class="meta-label">رقم الفاتورة · Invoice No.</div>
                <div class="meta-value num">{{ $invoice->invoice_number }}</div>
            </td>
            <td>
                <div class="meta-label">تاريخ الإصدار · Issue date</div>
                <div class="meta-value num">{{ $invoice->created_at?->format('Y-m-d') ?? '—' }}</div>
            </td>
            <td>
                <div class="meta-label">تاريخ الاستحقاق · Due date</div>
                <div class="meta-value num">{{ $invoice->due_date?->format('Y-m-d') ?? '—' }}</div>
            </td>
        </tr>
    </table>

    {{-- Line items --}}
    <table class="items">
        <thead>
            <tr>
                <th style="width: 46%;">الوصف · Description</th>
                <th style="width: 30%;">الفترة · Period</th>
                <th style="width: 24%;">المبلغ · Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="desc-title">اشتراك شهري · Monthly subscription</div>
                    @if ($seat?->seat_number)
                        <div class="desc-sub">المقعد · Seat: <span class="num">{{ $seat->seat_number }}</span></div>
                    @endif
                </td>
                <td class="num">{{ $period }}</td>
                <td class="num">{{ $money($lineAmount) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Totals --}}
    <table class="totals">
        <tr>
            <td class="spacer"></td>
            <td class="t-label">المجموع الفرعي · Subtotal</td>
            <td class="t-value num">{{ $money($lineAmount) }}</td>
        </tr>
        <tr class="grand">
            <td class="spacer"></td>
            <td class="t-label">الإجمالي · Total</td>
            <td class="t-value num">{{ $money($invoice->amount) }}</td>
        </tr>
    </table>

    {{-- Footer --}}
    <div class="footer">
        <div class="footer-rule"></div>
        <table class="footer-dates">
            <tr>
                <td style="text-align: right;">
                    تاريخ الإصدار · Issued: <span class="num">{{ $invoice->created_at?->format('Y-m-d') ?? '—' }}</span>
                </td>
                <td style="text-align: left;">
                    @if ($isPaid && $invoice->paid_at)
                        تاريخ الدفع · Paid: <span class="num">{{ $invoice->paid_at->format('Y-m-d') }}</span>
                    @else
                        تاريخ الاستحقاق · Due: <span class="num">{{ $invoice->due_date?->format('Y-m-d') ?? '—' }}</span>
                    @endif
                </td>
            </tr>
        </table>
        <div class="footer-thanks">
            شكراً لاشتراكك معنا · Thank you for your subscription.
        </div>
    </div>

</body>
</html>
