@php
    /** @var \App\Models\CreditNote $creditNote */
    $invoice = $creditNote->invoice;
    $workspace = $invoice?->subscription?->workspace;
    $customer = $invoice?->customer;
    // Only what was fixed when the note was issued: a numbered fiscal document must not change
    // afterwards. What the customer is owed back moves with payments, so it is shown on the invoice.

    // Cairo lacks the shekel glyph (U+20AA); render it in DejaVu Sans like the invoice does.
    $money = static fn ($value): string =>
        '<span class="num">' . number_format((float) $value, 2) . '</span> <span class="shekel">₪</span>';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 16mm 15mm; }
        body { font-family: 'cairo', sans-serif; font-size: 11pt; color: #1f2937; direction: rtl; }
        h1 { font-size: 20pt; margin: 0 0 4mm 0; color: #b91c1c; }
        .muted { color: #6b7280; }
        .shekel { font-family: 'dejavusans', sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 6mm; }
        td { padding: 3mm 2mm; border-bottom: 1px solid #e5e7eb; }
        td.label { width: 35%; color: #6b7280; }
        .total td { font-weight: bold; font-size: 13pt; border-bottom: 2px solid #1f2937; }
    </style>
</head>
<body>
    <h1>إشعار دائن / Credit note</h1>
    @if($workspace)
        <div class="muted">{{ $workspace->name }}</div>
    @endif

    <table>
        <tr><td class="label">رقم الإشعار / Number</td><td>{{ $creditNote->number }}</td></tr>
        <tr><td class="label">تاريخ الإصدار / Issued</td><td>{{ $creditNote->issued_at?->format('Y-m-d') }}</td></tr>
        <tr><td class="label">الفاتورة الأصلية / Invoice</td><td>{{ $invoice?->invoice_number }}</td></tr>
        <tr><td class="label">العميل / Customer</td><td>{{ $customer?->name }}</td></tr>
        <tr><td class="label">السبب / Reason</td><td>{{ $creditNote->reason }}</td></tr>
        <tr class="total"><td class="label">مبلغ الإشعار / Credit amount</td><td>{!! $money($creditNote->amount) !!}</td></tr>
    </table>
</body>
</html>
