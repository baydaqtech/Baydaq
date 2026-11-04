@php($isLanding = ($page['component'] ?? null) === 'Landing')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('baydaq.locales.'.app()->getLocale(), 'rtl') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title inertia>{{ __('common.meta.title') }}</title>
    <meta name="description" content="{{ __('common.meta.description') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ __('common.brand') }}">
    <meta property="og:title" content="{{ __('common.meta.og_title') }}">
    <meta property="og:description" content="{{ __('common.meta.og_description') }}">
    <meta property="og:image" content="{{ asset('brand/logo-horizontal.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    @foreach (array_keys(config('baydaq.locales')) as $code)
    <link rel="alternate" hreflang="{{ $code }}" href="{{ url()->current().'?lang='.$code }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ url()->current() }}">
    <link rel="icon" href="{{ asset('brand/favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('brand/favicon-32.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('brand/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Alexandria:wght@300;400;500;700;800;900&family=Aref+Ruqaa:wght@700&family=JetBrains+Mono:wght@400;500&display=swap">
    @if ($isLanding)
    <script>
        // Pause the hero animation while the loading screen is up (same as the prototype).
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.classList.add('loading');
        }
    </script>
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body>
@include('partials.symbols')
@if ($isLanding)
@include('partials.loader')
@endif
@inertia
@production
{{-- Cloudflare Web Analytics: visits only, no cookies. Production only, so local testing isn't counted. --}}
<script type="module" src="https://static.cloudflareinsights.com/beacon.min.js" data-cf-beacon='{"token": "1c173adda3ff455d946e18b84e9b963a"}'></script>
@endproduction
</body>
</html>
