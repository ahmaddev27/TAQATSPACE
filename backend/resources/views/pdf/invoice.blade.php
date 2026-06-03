@php
    /** @var \App\Models\Invoice $invoice */
    $subscription = $invoice->subscription;
    $member = $subscription?->member;
    $workspace = $subscription?->workspace;
    $seat = $subscription?->seat;

    $isPaid = $invoice->status === \App\Enums\InvoiceStatus::Paid;

    $period = trim(
        ($subscription?->start_date?->format('Y-m-d') ?? '—')
        . '  →  '
        . ($subscription?->end_date?->format('Y-m-d') ?? '—')
    );

    $money = static fn ($value): string => '₪ ' . number_format((float) $value, 2);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body {
            direction: rtl;
            text-align: right;
            color: #1a1a1a;
            font-size: 12px;
            margin: 0;
            padding: 32px;
        }
        .num { direction: ltr; unicode-bidi: embed; }
        .header {
            border-bottom: 3px solid #16a34a;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .brand {
            font-size: 26px;
            font-weight: bold;
            color: #16a34a;
        }
        .brand-ar {
            font-size: 14px;
            color: #555;
            margin-top: 2px;
        }
        .meta-table, .billto-table, .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td { padding: 3px 0; vertical-align: top; }
        .meta-label { color: #666; }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            margin: 20px 0 8px;
            color: #16a34a;
        }
        .billto {
            background: #f6f8f6;
            border: 1px solid #e2e8e2;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 8px;
        }
        .items-table th, .items-table td {
            border: 1px solid #d9d9d9;
            padding: 8px 10px;
            text-align: right;
        }
        .items-table th {
            background: #16a34a;
            color: #ffffff;
            font-weight: bold;
        }
        .total-box {
            margin-top: 18px;
            text-align: left;
        }
        .total-label {
            font-size: 13px;
            color: #666;
        }
        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #16a34a;
        }
        .status-pill {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-paid { background: #dcfce7; color: #15803d; }
        .status-pending { background: #fef9c3; color: #a16207; }
        .status-overdue { background: #fee2e2; color: #b91c1c; }
        .watermark {
            position: absolute;
            top: 320px;
            right: 130px;
            font-size: 96px;
            font-weight: bold;
            color: #16a34a;
            opacity: 0.12;
            transform: rotate(-30deg);
        }
        .footer {
            margin-top: 40px;
            padding-top: 14px;
            border-top: 1px solid #e2e8e2;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>

    @if ($isPaid)
        <div class="watermark">PAID</div>
    @endif

    <div class="header">
        <table style="width:100%;">
            <tr>
                <td style="text-align:right;">
                    <div class="brand">TAQAT.space</div>
                    <div class="brand-ar">طاقة لمساحات العمل المشتركة</div>
                </td>
                <td style="text-align:left;">
                    <div class="section-title" style="margin:0;">فاتورة / INVOICE</div>
                    <div class="num">{{ $invoice->invoice_number }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width:50%;">
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">رقم الفاتورة / Invoice No.</td>
                        <td class="num">{{ $invoice->invoice_number }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">تاريخ الإصدار / Issue date</td>
                        <td class="num">{{ $invoice->created_at?->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">تاريخ الاستحقاق / Due date</td>
                        <td class="num">{{ $invoice->due_date?->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                </table>
            </td>
            <td style="width:50%; text-align:left; vertical-align:top;">
                <span class="status-pill status-{{ $invoice->status->value }}">
                    @switch($invoice->status->value)
                        @case('paid') مدفوع / PAID @break
                        @case('overdue') متأخر / OVERDUE @break
                        @case('cancelled') ملغى / CANCELLED @break
                        @default قيد الانتظار / PENDING
                    @endswitch
                </span>
                @if ($isPaid && $invoice->paid_at)
                    <div style="margin-top:6px; color:#15803d;">
                        تاريخ الدفع / Paid: <span class="num">{{ $invoice->paid_at->format('Y-m-d') }}</span>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <div class="section-title">إلى / Bill to</div>
    <div class="billto">
        <div><strong>{{ $member?->name ?? '—' }}</strong></div>
        @if ($member?->email)
            <div class="num" style="text-align:right;">{{ $member->email }}</div>
        @endif
        @if ($member?->phone)
            <div class="num" style="text-align:right;">{{ $member->phone }}</div>
        @endif
    </div>

    <div class="section-title">مساحة العمل / Workspace</div>
    <div class="billto">
        <div><strong>{{ $workspace?->name ?? '—' }}</strong></div>
        @if ($workspace?->address)
            <div>{{ $workspace->address }}</div>
        @endif
        @if ($workspace?->city)
            <div>{{ $workspace->city }}</div>
        @endif
    </div>

    <div class="section-title">التفاصيل / Details</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>الوصف / Description</th>
                <th>الفترة / Period</th>
                <th>المقعد / Seat</th>
                <th>المبلغ / Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>اشتراك شهري / Monthly subscription</td>
                <td class="num" style="text-align:center;">{{ $period }}</td>
                <td class="num" style="text-align:center;">{{ $seat?->seat_number ?? '—' }}</td>
                <td class="num" style="text-align:left;">{{ $money($subscription?->monthly_price ?? $invoice->amount) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="total-box">
        <div class="total-label">الإجمالي / Total</div>
        <div class="total-amount num">{{ $money($invoice->amount) }}</div>
    </div>

    <div class="footer">
        <div>شكراً لاشتراكك معنا</div>
        <div>Thank you for your subscription.</div>
    </div>

</body>
</html>
