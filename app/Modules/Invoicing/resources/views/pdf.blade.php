@php
    $ar = fn (string $key) => __("invoicing.pdf.{$key}", [], 'ar');
    $ltr = fn (?string $text) => "\u{202A}".e($text)."\u{202C}";
    $en = fn (string $key) => $ltr(__("invoicing.pdf.{$key}", [], 'en'));
    $money = fn ($amount) => number_format((float) $amount, 2).' '.$invoice->currency;
    $titleKey = match ($invoice->kind->value) { 'credit_note' => 'credit_note', 'debit_note' => 'debit_note', default => $invoice->type->value === 'simplified' ? 'simplified_tax_invoice' : 'tax_invoice' };
    $customer = $invoice->customer;
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<style>
    body { font-family: plexarabic; font-size: 9.5pt; color: #2A1A0E; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; }
    .en { color: #6B5238; font-size: 8pt; direction: ltr; text-align: right; }
    .muted { color: #6B5238; }
    .title { font-size: 20pt; font-weight: bold; color: #3B2414; }
    .label { color: #6B5238; padding: 2pt 0; }
    .items th { background-color: #3B2414; color: #F6EBD6; font-weight: bold; padding: 6pt; text-align: right; font-size: 8.5pt; }
    .items td { padding: 6pt; border-bottom: 0.5pt solid #E6D7BF; }
    .num { text-align: left; direction: ltr; }
    .totals td { padding: 5pt 6pt; border-bottom: 0.5pt solid #E6D7BF; }
    .grand td { background-color: #3B2414; color: #F6EBD6; font-weight: bold; font-size: 11pt; padding: 8pt 6pt; border: 0; }
    .box { border: 0.5pt solid #E6D7BF; padding: 8pt; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width: 55%;">
            <div class="title">{{ $ar($titleKey) }}</div>
            <div class="en" dir="ltr" style="text-align: left; font-size: 11pt;">{!! $en($titleKey) !!}</div>
            <table style="margin-top: 8pt; width: auto;">
                <tr><td class="label">{{ $ar('number') }} / {!! $en('number') !!}</td><td class="num" style="padding: 2pt 8pt; font-weight: bold;">{{ $invoice->number ?? '—' }}</td></tr>
                <tr><td class="label">{{ $ar('date') }} / {!! $en('date') !!}</td><td class="num" style="padding: 2pt 8pt;">{{ $invoice->issue_date->toDateString() }} {{ $invoice->issue_time }}</td></tr>
                @if ($invoice->related)<tr><td class="label">{{ __('invoicing.invoices.fields.related', [], 'ar') }}</td><td class="num" style="padding: 2pt 8pt;">{{ $invoice->related->number }}</td></tr>@endif
            </table>
        </td>
        <td style="width: 45%; text-align: left;">
            @if ($invoice->qr && class_exists(\Mpdf\QrCode\QrCode::class))
                <barcode code="{{ $invoice->qr }}" type="QR" size="1.05" error="M" disableborder="1" />
            @elseif ($invoice->qr)
                {{-- The mpdf/qrcode package draws the QR image; until it is installed the payload is printed. --}}
                <div class="num" style="font-size: 6pt; word-break: break-all; color: #6B5238;">{{ $invoice->qr }}</div>
            @endif
        </td>
    </tr>
</table>

<div style="border-bottom: 2pt solid #C8902F; margin: 8pt 0 10pt;"></div>

<table>
    <tr>
        <td style="width: 50%; padding-left: 6pt;" class="box">
            <div class="muted">{{ $ar('seller') }} · {!! $en('seller') !!}</div>
            <div style="font-weight: bold; margin-top: 3pt;">{{ $company?->nameAr }}</div>
            <div class="en" dir="ltr" style="text-align: right;">{!! $ltr($company?->nameEn) !!}</div>
            <div class="muted" style="margin-top: 3pt;">{{ implode('، ', array_filter([$company?->address['building_number'] ?? null, $company?->address['street'] ?? null, $company?->address['district'] ?? null, $company?->address['city'] ?? null, $company?->address['postal_code'] ?? null])) }}</div>
            <div>{{ $ar('vat_no') }}: <span class="num" style="display: inline;">{{ $company?->vatNumber }}</span></div>
            @if ($company?->crNumber)<div>{{ $ar('cr_no') }}: <span class="num" style="display: inline;">{{ $company->crNumber }}</span></div>@endif
        </td>
        <td style="width: 50%;" class="box">
            <div class="muted">{{ $ar('buyer') }} · {!! $en('buyer') !!}</div>
            @if ($customer)
                <div style="font-weight: bold; margin-top: 3pt;">{{ $customer->name_ar }}</div>
                <div class="en" dir="ltr" style="text-align: right;">{!! $ltr($customer->name_en) !!}</div>
                <div class="muted" style="margin-top: 3pt;">{{ implode('، ', array_filter([$customer->building_number, $customer->street, $customer->district, $customer->city, $customer->postal_code])) }}</div>
                @if ($customer->vat_number)<div>{{ $ar('vat_no') }}: <span class="num" style="display: inline;">{{ $customer->vat_number }}</span></div>@endif
            @else
                <div style="margin-top: 3pt;">{{ __('invoicing.invoices.customer_walkin', [], 'ar') }}</div>
            @endif
        </td>
    </tr>
</table>

<table class="items" style="margin-top: 12pt;">
    <thead>
        <tr>
            <th style="width: 44%;">{{ $ar('description') }}<div class="en" style="color: #E9D6B3;">{!! $en('description') !!}</div></th>
            <th>{{ $ar('qty') }}<div class="en" style="color: #E9D6B3;">{!! $en('qty') !!}</div></th>
            <th>{{ $ar('unit_price') }}<div class="en" style="color: #E9D6B3;">{!! $en('unit_price') !!}</div></th>
            <th>{{ $ar('vat') }}<div class="en" style="color: #E9D6B3;">{!! $en('vat') !!}</div></th>
            <th>{{ $ar('total') }}<div class="en" style="color: #E9D6B3;">{!! $en('total') !!}</div></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->lines as $line)
            <tr>
                <td>{{ $line->description }}</td>
                <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity, 3), '0'), '.') }}</td>
                <td class="num">{{ number_format((float) $line->unit_price, 2) }}</td>
                <td class="num">{{ number_format((float) $line->vat, 2) }} ({{ rtrim(rtrim(number_format((float) $line->vat_rate, 2), '0'), '.') }}%)</td>
                <td class="num">{{ number_format((float) $line->total, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table style="margin-top: 10pt;">
    <tr>
        <td style="width: 55%;"></td>
        <td style="width: 45%;">
            <table class="totals">
                <tr><td>{{ $ar('subtotal') }}<div class="en">{!! $en('subtotal') !!}</div></td><td class="num">{{ $money($invoice->subtotal - $invoice->discount) }}</td></tr>
                @if ((float) $invoice->discount > 0)<tr><td>{{ $ar('discount') }}<div class="en">{!! $en('discount') !!}</div></td><td class="num">{{ $money($invoice->discount) }}</td></tr>@endif
                <tr><td>{{ $ar('vat_total') }}<div class="en">{!! $en('vat_total') !!}</div></td><td class="num">{{ $money($invoice->vat) }}</td></tr>
                @if ((float) $invoice->rounding !== 0.0)<tr><td>{{ $ar('rounding') }}<div class="en">{!! $en('rounding') !!}</div></td><td class="num">{{ $money($invoice->rounding) }}</td></tr>@endif
                <tr class="grand"><td>{{ $ar('grand_total') }}<div class="en" style="color: #E9D6B3;">{!! $en('grand_total') !!}</div></td><td class="num">{{ $money($invoice->payableAmount()) }}</td></tr>
            </table>
        </td>
    </tr>
</table>
@if ($invoice->note)<p class="muted" style="margin-top: 10pt;">{{ $invoice->note }}</p>@endif
</body>
</html>
