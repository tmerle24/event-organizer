@php
    $ogLocale = [
        'de' => 'de_DE', 'en' => 'en_GB', 'fr' => 'fr_FR', 'es' => 'es_ES', 'nl' => 'nl_NL',
    ][app()->getLocale()] ?? 'de_DE';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />

        {{-- favicon.ico bleibt zusätzlich im Wurzelverzeichnis: Browser fragen den
             Pfad auch ohne <link> an, ein 404 pro Aufruf ist unnötiges Log-Rauschen. --}}
        <link rel="icon" href="/favicon.ico" sizes="32x32" />
        <link rel="icon" href="/icon/favicon.svg" type="image/svg+xml" />
        <link rel="apple-touch-icon" href="/icon/apple-touch-icon.png" />
        <link rel="manifest" href="/site.webmanifest" />
        <meta name="theme-color" content="#5B4BE8" />

        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

        <title inertia>{{ config('app.name') }}</title>
        <meta name="description" content="{{ $ogDescription ?? config('brand.claim') }}" />
        <link rel="canonical" href="{{ url()->current() }}" />
        {{-- Event-Seiten sind privat und gehoeren nicht in den Index. --}}
        @if (request()->is('e/*') || request()->is('t/*'))
            <meta name="robots" content="noindex, nofollow" />
        @endif

        {{--
            Link-Vorschau. Der WhatsApp-Crawler führt kein JavaScript aus, deshalb
            stehen die Tags serverseitig im HTML. Bild muss absolut, https und
            unter 600 KB sein — siehe brand/og/README.md.
        --}}
        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="{{ config('app.name') }}" />
        {{-- Auf Event-Seiten stehen hier Titel und Termin, sonst die Marke. --}}
        <meta property="og:title" content="{{ $ogTitle ?? config('app.name') }}" />
        <meta property="og:description" content="{{ $ogDescription ?? config('brand.share_text') }}" />
        <meta property="og:url" content="{{ url()->current() }}" />
        <meta property="og:locale" content="{{ $ogLocale }}" />
        <meta property="og:image" content="{{ url('/og/og-image.png') }}" />
        <meta property="og:image:type" content="image/png" />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />
        <meta property="og:image:alt" content="{{ config('app.name') }} – {{ config('brand.tagline') }}" />

        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="{{ $ogTitle ?? config('app.name') }}" />
        <meta name="twitter:description" content="{{ $ogDescription ?? config('brand.share_text') }}" />
        <meta name="twitter:image" content="{{ url('/og/og-image.png') }}" />

        @routes
        @vite(['resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
