<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />

        <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
        <link rel="icon" href="/favicon.ico" sizes="any" />
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png" />
        <link rel="manifest" href="/site.webmanifest" />
        <meta name="theme-color" content="#5B4BE8" />

        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

        <title inertia>{{ config('app.name') }}</title>
        <meta name="description" content="{{ config('brand.claim') }}" />
        <link rel="canonical" href="{{ url()->current() }}" />
        {{-- Event-Seiten sind privat und gehoeren nicht in den Index. --}}
        @if (request()->is('e/*') || request()->is('t/*'))
            <meta name="robots" content="noindex, nofollow" />
        @endif

        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="{{ config('app.name') }}" />
        <meta property="og:title" content="{{ config('app.name') }} — {{ config('brand.tagline') }}" />
        <meta property="og:description" content="{{ config('brand.claim') }}" />
        <meta property="og:image" content="{{ url('/icons/icon-512.png') }}" />
        <meta property="og:url" content="{{ url()->current() }}" />

        <meta name="twitter:card" content="summary" />
        <meta name="twitter:title" content="{{ config('app.name') }}" />
        <meta name="twitter:description" content="{{ config('brand.claim') }}" />
        <meta name="twitter:image" content="{{ url('/icons/icon-512.png') }}" />

        @routes
        @vite(['resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
