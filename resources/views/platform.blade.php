<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('baydaq.locales.'.app()->getLocale(), 'rtl') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title inertia>{{ __('common.platform') }}</title>
    <link rel="icon" href="{{ asset('brand/favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('brand/favicon-32.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('brand/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Alexandria:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">
    @vite(['resources/css/platform.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body class="min-h-svh antialiased">
@inertia
</body>
</html>
