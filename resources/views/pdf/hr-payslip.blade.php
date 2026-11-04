@php
    $t = fn (string $key, array $replace = []) => __("hr.payslips.pdf.{$key}", $replace, $locale);
    $rtl = $locale === 'ar';
    $employee = $line->employee;
    $month = $line->run->pay_month->copy()->locale($locale)->translatedFormat('F Y');
    $money = fn ($amount) => number_format((float) $amount, 2);
    $earnings = collect($line->breakdown ?? [])->filter(fn ($item) => ! str_starts_with((string) $item['amount'], '-'));
    $taken = collect($line->breakdown ?? [])->filter(fn ($item) => str_starts_with((string) $item['amount'], '-'));
    $label = fn ($item) => $item["label_{$locale}"] ?? $item['label_ar'] ?? '';
    $end = $rtl ? 'left' : 'right';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: plexarabic; font-size: 10.5pt; color: #2A1A0E; line-height: 1.6; }
    .company { font-size: 15pt; font-weight: bold; color: #3B2414; }
    .muted { color: #6B5238; font-size: 9pt; }
    .title { font-size: 16pt; font-weight: bold; color: #3B2414; margin: 18pt 0 10pt; }
    table { border-collapse: collapse; width: 100%; }
    .facts td { padding: 3pt 6pt; }
    .facts .label { color: #6B5238; width: 30%; }
    .lines th { text-align: {{ $rtl ? 'right' : 'left' }}; color: #6B5238; font-weight: normal; border-bottom: 0.75pt solid #C9B48F; padding: 4pt 6pt; }
    .lines td { padding: 4pt 6pt; border-bottom: 0.5pt solid #E6D7BF; }
    .amount { text-align: {{ $end }}; white-space: nowrap; }
    .total td { font-weight: bold; border-top: 1pt solid #3B2414; border-bottom: none; }
    .net { margin-top: 14pt; background: #F5EEDD; padding: 10pt; font-size: 13pt; font-weight: bold; }
</style>
</head>
<body>
<table>
    <tr>
        <td>
            <div class="company">{{ $company?->name ?? '' }}</div>
            @if ($company?->crNumber)<div class="muted">{{ $t('cr') }}: {{ $company->crNumber }}</div>@endif
        </td>
        <td style="text-align: {{ $end }};" class="muted">{{ $t('month') }}: {{ $month }}</td>
    </tr>
</table>

<div class="title">{{ $t('title') }}</div>

<table class="facts">
    <tr><td class="label">{{ $t('employee') }}</td><td>{{ $employee->localized('name') }}</td></tr>
    <tr><td class="label">{{ $t('number') }}</td><td>{{ $employee->employee_number }}</td></tr>
    @if ($employee->job)<tr><td class="label">{{ $t('job') }}</td><td>{{ $employee->job->localized('name') }}</td></tr>@endif
    @if ($branch)<tr><td class="label">{{ $t('branch') }}</td><td>{{ $branch->name }}</td></tr>@endif
    <tr><td class="label">{{ $t('days') }}</td><td>{{ $line->working_days }}@if ((float) $line->unpaid_leave_days > 0) · {{ $t('unpaid_days', ['days' => rtrim(rtrim((string) $line->unpaid_leave_days, '0'), '.')]) }}@endif</td></tr>
</table>

<table class="lines" style="margin-top: 12pt;">
    <tr><th>{{ $t('earnings') }}</th><th class="amount">{{ $t('amount') }}</th></tr>
    @foreach ($earnings as $item)
        <tr><td>{{ $label($item) }}</td><td class="amount">{{ $money($item['amount']) }}</td></tr>
    @endforeach
    <tr class="total"><td>{{ $t('gross') }}</td><td class="amount">{{ $money($line->gross) }}</td></tr>
</table>

<table class="lines" style="margin-top: 12pt;">
    <tr><th>{{ $t('deductions') }}</th><th class="amount">{{ $t('amount') }}</th></tr>
    @foreach ($taken as $item)
        <tr><td>{{ $label($item) }}</td><td class="amount">{{ $money(ltrim((string) $item['amount'], '-')) }}</td></tr>
    @endforeach
    <tr class="total"><td>{{ $t('taken') }}</td><td class="amount">{{ $money((float) $line->gross - (float) $line->net) }}</td></tr>
</table>

<div class="net">
    <table><tr><td>{{ $t('net') }}</td><td class="amount">{{ $money($line->net) }} {{ $t('currency') }}</td></tr></table>
</div>

<p class="muted" style="margin-top: 16pt;">
    {{ $line->run->paid_on ? $t('paid_on', ['date' => $line->run->paid_on->copy()->locale($locale)->translatedFormat('j F Y')]) : $t('approved') }}
</p>
</body>
</html>
