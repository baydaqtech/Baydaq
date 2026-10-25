<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('common.payments.test_title') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #F6EBD6; color: #3B2414; font-family: system-ui, sans-serif; }
        main { background: #fff; border-radius: 16px; padding: 32px; width: min(92vw, 420px); box-shadow: 0 10px 30px rgba(59, 36, 20, .12); }
        h1 { font-size: 1.1rem; margin: 0 0 4px; }
        p { color: #6B5238; margin: 4px 0; }
        .amount { font-size: 2rem; font-weight: 700; margin: 16px 0; color: #3B2414; }
        button { width: 100%; border: 0; border-radius: 10px; padding: 14px; background: #C8902F; color: #fff; font-size: 1rem; cursor: pointer; }
        .note { font-size: .8rem; margin-top: 16px; }
    </style>
</head>
<body>
<main>
    <h1>{{ __('common.payments.test_title') }}</h1>
    <p>{{ $payment->description }}</p>
    <div class="amount" dir="ltr">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</div>
    @if ($payment->status === 'pending')
        <form method="post" action="{{ route('payments.fake.pay', $payment->token) }}">
            @csrf
            <button type="submit">{{ __('common.payments.test_pay') }}</button>
        </form>
    @else
        <p>{{ __('common.payments.status.'.$payment->status) }}</p>
    @endif
    <p class="note">{{ __('common.payments.test_note') }}</p>
</main>
</body>
</html>
