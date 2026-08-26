<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />

        <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
        <meta name="theme-color" content="#1f2430" />

        <title inertia>{{ config('app.name') }}</title>
        <meta name="description" content="{{ __('Termin finden, Plan machen, fertig. Ohne Anmeldung, in Sekunden.') }}" />
        <link rel="canonical" href="{{ url()->current() }}" />
        {{-- Event-Seiten sind privat und gehoeren nicht in den Index. --}}
        @if (request()->is('e/*') || request()->is('t/*'))
            <meta name="robots" content="noindex, nofollow" />
        @endif

        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="{{ config('app.name') }}" />
        <meta property="og:title" content="{{ config('app.name') }}" />
        <meta property="og:description" content="Termin finden, Plan machen, fertig. Ohne Anmeldung, in Sekunden." />
        <meta property="og:url" content="{{ url()->current() }}" />
        <meta name="twitter:card" content="summary" />

        @routes
        @vite(['resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-body antialiased">
        @inertia
    </body>
</html>
