<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title inertia>بيدق تكنولوجي — مواقع وتطبيقات وحلول ذكاء اصطناعي</title>
    <meta name="description" content="بيدق تكنولوجي: نصمّم ونبني المواقع والتطبيقات وحلول الذكاء الاصطناعي للشركات، ونبقى معك بعد الإطلاق.">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="بيدق تكنولوجي">
    <meta property="og:title" content="بيدق تكنولوجي — كل حركة محسوبة">
    <meta property="og:description" content="نصمّم ونبني المواقع والتطبيقات وحلول الذكاء الاصطناعي للشركات، ونبقى معك بعد الإطلاق.">
    <meta property="og:image" content="{{ asset('brand/logo-horizontal.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="{{ asset('brand/favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('brand/favicon-32.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('brand/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Alexandria:wght@300;400;500;700;800;900&family=Aref+Ruqaa:wght@700&family=JetBrains+Mono:wght@400;500&display=swap">
    <script>
        // Pause the hero animation while the loading screen is up (same as the prototype).
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.classList.add('loading');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @inertiaHead
</head>
<body>
@include('partials.symbols')
@include('partials.loader')
@inertia
</body>
</html>
