@php
    $t = fn (string $key, array $replace = []) => __("hr.letters.pdf.{$key}", $replace, $locale);
    $rtl = $locale === 'ar';
    $field = fn (array $data, string $base) => $data["{$base}_{$locale}"] ?? null ?: ($data[$base.'_'.($rtl ? 'en' : 'ar')] ?? '');
    $company = $field($snapshot['company'], 'name');
    $employee = $snapshot['employee'];
    $name = $field($employee, 'name');
    $job = $field($employee, 'job');
    $date = fn (?string $value) => $value ? \Illuminate\Support\Carbon::parse($value)->locale($locale)->translatedFormat('j F Y') : '';
    $money = fn ($amount) => number_format((float) $amount, 2).' '.$t('currency');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: plexarabic; font-size: 11pt; color: #2A1A0E; line-height: 1.7; }
    .company { font-size: 16pt; font-weight: bold; color: #3B2414; }
    .muted { color: #6B5238; font-size: 9pt; }
    .title { font-size: 18pt; font-weight: bold; color: #3B2414; text-align: center; margin: 24pt 0 16pt; }
    table { border-collapse: collapse; width: 100%; }
    .facts td { padding: 5pt 8pt; border-bottom: 0.5pt solid #E6D7BF; }
    .facts .label { color: #6B5238; width: 35%; }
    .verify { margin-top: 30pt; }
    .stamp-revoked { color: #B42318; border: 1.5pt solid #B42318; padding: 4pt 10pt; font-weight: bold; text-align: center; }
</style>
</head>
<body>
<table>
    <tr>
        <td>
            <div class="company">{{ $company }}</div>
            @if ($snapshot['company']['cr_number'])<div class="muted">{{ $t('cr') }}: {{ $snapshot['company']['cr_number'] }}</div>@endif
        </td>
        <td style="text-align: {{ $rtl ? 'left' : 'right' }};" class="muted">
            {{ $t('reference') }}: {{ $letter->reference }}<br>
            {{ $t('date') }}: {{ $date($letter->issued_at->toDateString()) }}
        </td>
    </tr>
</table>

<div class="title">{{ $t($letter->kind->value.'_title') }}</div>

<p>{{ $letter->addressed_to ? $t('to', ['name' => $letter->addressed_to]) : $t('to_whom') }}</p>

<p>
@if ($letter->kind->value === 'salary')
    {{ $t('salary_body', ['company' => $company, 'name' => $name, 'job' => $job, 'since' => $date($employee['hired_on'])]) }}
@else
    {{ $employee['left_on']
        ? $t('experience_body_left', ['company' => $company, 'name' => $name, 'job' => $job, 'from' => $date($employee['hired_on']), 'to' => $date($employee['left_on'])])
        : $t('experience_body', ['company' => $company, 'name' => $name, 'job' => $job, 'from' => $date($employee['hired_on'])]) }}
@endif
</p>

<table class="facts">
    <tr><td class="label">{{ $t('employee') }}</td><td>{{ $name }}</td></tr>
    <tr><td class="label">{{ $t('number') }}</td><td>{{ $employee['number'] }}</td></tr>
    @if ($employee['nationality'])<tr><td class="label">{{ $t('nationality') }}</td><td>{{ $employee['nationality'] }}</td></tr>@endif
    @if ($employee['id_last4'])<tr><td class="label">{{ $t('id') }}</td><td>{{ "\u{202A}••••".$employee['id_last4']."\u{202C}" }}</td></tr>@endif
    @if ($job)<tr><td class="label">{{ $t('job') }}</td><td>{{ $job }}</td></tr>@endif
    @if ($snapshot['salary'])
        <tr><td class="label">{{ $t('basic') }}</td><td>{{ $money($snapshot['salary']['basic']) }}</td></tr>
        <tr><td class="label">{{ $t('allowances') }}</td><td>{{ $money($snapshot['salary']['allowances']) }}</td></tr>
        <tr><td class="label"><b>{{ $t('total') }}</b></td><td><b>{{ $money($snapshot['salary']['total']) }}</b></td></tr>
    @endif
</table>

<p>{{ $t('closing') }}</p>

@unless ($letter->isValid())<p class="stamp-revoked">{{ $t('revoked') }}</p>@endunless

<table class="verify">
    <tr>
        <td style="width: 32mm;"><img src="{{ $qr }}" style="width: 28mm; height: 28mm;"></td>
        <td class="muted">{{ $t('verify') }}<br><span dir="ltr">{{ $verifyUrl }}</span></td>
    </tr>
</table>
</body>
</html>
