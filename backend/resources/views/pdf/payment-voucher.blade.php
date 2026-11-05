@php
    /** @var \App\Models\PaymentVoucher $voucher */
    $workspace = $voucher->workspace;
    $customer = $voucher->customer;
    $currency = $workspace?->currency ?? '';

    $money = static fn ($value): string =>
        '<span class="num">' . number_format((float) $value, 2) . '</span> <span class="currency">' . e($currency) . '</span>';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 16mm 15mm; }
        body { font-family: 'cairo', sans-serif; font-size: 11pt; color: #1f2937; direction: rtl; }
        h1 { font-size: 20pt; margin: 0 0 4mm 0; color: #1d4ed8; }
        .muted { color: #6b7280; }
        .void { color: #b91c1c; font-weight: bold; margin-top: 3mm; }
        .currency { font-family: 'dejavusans', sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 6mm; }
        td { padding: 3mm 2mm; border-bottom: 1px solid #e5e7eb; }
        td.label { width: 35%; color: #6b7280; }
        .total td { font-weight: bold; font-size: 13pt; border-bottom: 2px solid #1f2937; }
    </style>
</head>
<body>
    <h1>سند صرف / Payment voucher</h1>
    @if($workspace)
        <div class="muted">{{ $workspace->name }}</div>
    @endif
    @if($voucher->isVoided())
        <div class="void">ملغى / Void — {{ $voucher->void_reason }}</div>
    @endif

    <table>
        <tr><td class="label">رقم السند / Number</td><td>{{ $voucher->number }}</td></tr>
        <tr><td class="label">تاريخ الصرف / Paid</td><td>{{ $voucher->paid_at?->format('Y-m-d') }}</td></tr>
        <tr><td class="label">العميل / Customer</td><td>{{ $customer?->name }}</td></tr>
        <tr><td class="label">طريقة الدفع / Method</td><td>{{ $voucher->method->value }}</td></tr>
        @if($voucher->reference)
            <tr><td class="label">المرجع / Reference</td><td>{{ $voucher->reference }}</td></tr>
        @endif
        <tr><td class="label">السبب / Reason</td><td>{{ $voucher->reason }}</td></tr>
        <tr class="total"><td class="label">المبلغ / Amount</td><td>{!! $money($voucher->amount) !!}</td></tr>
    </table>
</body>
</html>
