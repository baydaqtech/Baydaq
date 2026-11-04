@php
    $ar = fn (string $key, array $replace = []) => __("invoices.pdf.{$key}", $replace, 'ar');
    // English runs are wrapped in LRE…PDF marks so trailing dots and brackets stay put inside the Arabic page.
    $ltr = fn (?string $text) => "\u{202A}".e($text)."\u{202C}";
    $en = fn (string $key, array $replace = []) => $ltr(__("invoices.pdf.{$key}", $replace, 'en'));
    $enWith = fn (string $key, ?string $value) => $ltr(__("invoices.pdf.{$key}", [], 'en').': '.$value);
    $money = fn ($amount) => number_format((float) $amount, 2).' '.$invoice->currency;
    $rate = rtrim(rtrim(number_format((float) $invoice->vat_rate * 100, 2), '0'), '.');
    $issuerName = $issuer['company_name_ar'] ?? null ?: __('common.brand', [], 'ar');
    $issuerNameEn = $issuer['company_name_en'] ?? null ?: __('common.brand', [], 'en');
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
    .title { font-size: 24pt; font-weight: bold; color: #3B2414; }
    .label { color: #6B5238; padding: 3pt 0; }
    .value { padding: 3pt 0; text-align: left; direction: ltr; font-weight: bold; }
    .shade { background-color: #F6EBD6; padding: 9pt 11pt; }
    .items th { background-color: #3B2414; color: #F6EBD6; font-weight: bold; padding: 6pt; text-align: right; font-size: 8.5pt; }
    .en-light { color: #E9D6B3; font-size: 8pt; text-align: right; }
    .items td { padding: 6pt; border-bottom: 0.5pt solid #E6D7BF; }
    .num { text-align: left; direction: ltr; }
    .totals td { padding: 5pt 6pt; border-bottom: 0.5pt solid #E6D7BF; }
    .grand td { background-color: #3B2414; color: #F6EBD6; font-weight: bold; font-size: 11pt; padding: 8pt 6pt; border: 0; }
    .stamp { color: #1F7A3A; border: 1.5pt solid #1F7A3A; padding: 4pt 10pt; font-weight: bold; font-size: 11pt; text-align: center; }
    .foot { font-size: 8pt; color: #6B5238; text-align: center; }
</style>
</head>
<body>

<table>
    <tr>
        <td style="width: 60%;">
            <img src="{{ $logo }}" style="height: 34pt;" alt="">
            <div style="margin-top: 8pt; font-weight: bold;">{{ $issuerName }}</div>
            <div class="en" dir="ltr">{!! $ltr($issuerNameEn) !!}</div>
            @if (! empty($issuer['address_ar']))<div class="muted">{{ $issuer['address_ar'] }}</div>@endif
            @if (! empty($issuer['address_en']))<div class="en" dir="ltr">{!! $ltr($issuer['address_en']) !!}</div>@endif
        </td>
        <td style="width: 40%; text-align: left;">
            <div class="title">{{ $ar('title') }}</div>
            <div dir="ltr" style="color: #6B5238; font-size: 12pt; text-align: left;">{!! $en('title') !!}</div>
            @if ($invoice->status === \App\Enums\InvoiceStatus::Paid)
                <table style="width: auto; margin-top: 8pt;"><tr><td class="stamp">{{ $ar('paid_stamp') }} · {!! $en('paid_stamp') !!}</td></tr></table>
            @endif
        </td>
    </tr>
</table>

<div style="border-bottom: 2pt solid #C8902F; margin: 10pt 0 0;"></div>

<table style="margin-top: 12pt;">
    <tr>
        <td style="width: 54%;" class="shade">
            <div class="muted">{{ $ar('bill_to') }}</div>
            <div class="en" dir="ltr">{!! $en('bill_to') !!}</div>
            <div style="font-weight: bold; font-size: 11pt; margin-top: 4pt;">{{ $invoice->client->name_ar }}</div>
            <div class="en" dir="ltr" style="font-size: 9pt;">{!! $ltr($invoice->client->name_en) !!}</div>
            @if ($invoice->client->contact_name)<div style="margin-top: 3pt;">{{ $invoice->client->contact_name }}</div>@endif
            @if ($invoice->client->contact_email)<div class="en" dir="ltr" style="font-size: 9pt;">{!! $ltr($invoice->client->contact_email) !!}</div>@endif
            @if ($invoice->project)
                <div style="margin-top: 5pt;">{{ $ar('project') }}: {{ $invoice->project->name_ar }}</div>
                <div class="en" dir="ltr">{!! $enWith('project', $invoice->project->name_en) !!}</div>
            @endif
        </td>
        <td style="width: 4%;"></td>
        <td style="width: 42%;">
            <table>
                <tr><td class="label">{{ $ar('number') }}<div class="en" dir="ltr">{!! $en('number') !!}</div></td><td class="value">{{ $invoice->number }}</td></tr>
                <tr><td class="label">{{ $ar('issue_date') }}<div class="en" dir="ltr">{!! $en('issue_date') !!}</div></td><td class="value">{{ $invoice->issue_date->format('Y-m-d') }}</td></tr>
                <tr><td class="label">{{ $ar('due_date') }}<div class="en" dir="ltr">{!! $en('due_date') !!}</div></td><td class="value">{{ $invoice->due_date->format('Y-m-d') }}</td></tr>
                @if (! empty($issuer['vat_number']))
                    <tr><td class="label">{{ $ar('vat_number') }}<div class="en" dir="ltr">{!! $en('vat_number') !!}</div></td><td class="value">{{ $issuer['vat_number'] }}</td></tr>
                @endif
                @if (! empty($issuer['cr_number']))
                    <tr><td class="label">{{ $ar('cr_number') }}<div class="en" dir="ltr">{!! $en('cr_number') !!}</div></td><td class="value">{{ $issuer['cr_number'] }}</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<table class="items" style="margin-top: 16pt;">
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 47%;">{{ $ar('description') }}<div class="en-light" dir="ltr">{!! $en('description') !!}</div></th>
            <th style="width: 10%;">{{ $ar('quantity') }}<div class="en-light" dir="ltr">{!! $en('quantity') !!}</div></th>
            <th style="width: 19%;">{{ $ar('unit_price') }}<div class="en-light" dir="ltr">{!! $en('unit_price') !!}</div></th>
            <th style="width: 19%;">{{ $ar('line_total') }}<div class="en-light" dir="ltr">{!! $en('line_total') !!}</div></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->description }}</td>
                <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                <td class="num">{{ $money($item->unit_price) }}</td>
                <td class="num">{{ $money($item->total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table style="margin-top: 12pt;">
    <tr>
        <td style="width: 52%;">
            @if (! empty($issuer['iban']))
                <table>
                    <tr>
                        <td class="shade">
                            <div style="font-weight: bold;">{{ $ar('payment') }}</div>
                            <div class="en" dir="ltr">{!! $en('payment') !!}</div>
                            @if (! empty($issuer['bank_name']))
                                <div style="margin-top: 5pt;">{{ $ar('bank') }}</div>
                                <div class="en" dir="ltr">{!! $enWith('bank', $issuer['bank_name']) !!}</div>
                            @endif
                            @if (! empty($issuer['account_name']))
                                <div style="margin-top: 4pt;">{{ $ar('account_name') }}</div>
                                <div class="en" dir="ltr">{!! $enWith('account_name', $issuer['account_name']) !!}</div>
                            @endif
                            <div style="margin-top: 4pt;">{{ $ar('iban') }}</div>
                            <div class="en" dir="ltr" style="font-size: 9.5pt; color: #2A1A0E;">{!! $ltr($issuer['iban']) !!}</div>
                        </td>
                    </tr>
                </table>
            @endif
        </td>
        <td style="width: 4%;"></td>
        <td style="width: 44%;">
            <table class="totals">
                <tr><td>{{ $ar('subtotal') }}<div class="en" dir="ltr">{!! $en('subtotal') !!}</div></td><td class="num">{{ $money($invoice->subtotal) }}</td></tr>
                <tr><td>{{ $ar('vat', ['rate' => $rate]) }}<div class="en" dir="ltr">{!! $en('vat', ['rate' => $rate]) !!}</div></td><td class="num">{{ $money($invoice->vat) }}</td></tr>
                <tr class="grand"><td>{{ $ar('total') }}<div class="en-light" dir="ltr">{!! $en('total') !!}</div></td><td class="num">{{ $money($invoice->total) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if ($invoice->notes)
    <div style="margin-top: 16pt; font-weight: bold;">{{ $ar('notes') }}</div>
    <div class="en" dir="ltr">{!! $en('notes') !!}</div>
    <div style="margin-top: 3pt;">{!! nl2br(e($invoice->notes)) !!}</div>
@endif

@if (! empty($issuer['invoice_note']))
    <div class="foot" style="margin-top: 22pt;">{{ $issuer['invoice_note'] }}</div>
@endif

</body>
</html>
