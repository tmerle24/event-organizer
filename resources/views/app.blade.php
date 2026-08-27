@php
    /*
     * Nackte absolute URL ohne Query-String. Ein Versionsstempel war hier
     * kurzzeitig dran; der ist raus, weil er gegen einen Cache gedacht war,
     * den es nicht gibt — Vorschau-Dienste holen die Datei ohnehin frisch.
     * Ändert sich das Bild, bekommt es einen neuen Dateinamen.
     */
    $ogImage = url('/og/og-image.png');

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

        {{--
            Serverseitiger Titel. Inertia setzt ihn im Browser über <Head>, aber
            ein Crawler führt kein JavaScript aus und sah bisher auf jeder Seite
            nur "ORGDATE". Manche Clients — Teams etwa — beschriften einen
            eingefügten Link mit dem <title>; ohne echten Titel bleibt dort die
            nackte URL stehen.
        --}}
        <title inertia>{{ $pageTitle ?? config('app.name') }}</title>
        <meta name="description" content="{{ $ogDescription ?? config('brand.claim') }}" />
        <link rel="canonical" href="{{ url()->current() }}" />
        @if (config('brand.google_site_verification'))
            <meta name="google-site-verification" content="{{ config('brand.google_site_verification') }}" />
        @endif
        {{-- Event-Seiten sind privat und gehoeren nicht in den Index. --}}
        @if (request()->is('e/*') || request()->is('t/*'))
            <meta name="robots" content="noindex" />
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
        <meta property="og:image" content="{{ $ogImage }}" />
        {{-- Microsofts Crawler (Teams/Skype) wertet secure_url gesondert aus.
             Nur ausgeben, wenn die Seite wirklich über https läuft — sonst
             stünde hier eine URL, die es nicht gibt. --}}
        @if (str_starts_with(config('app.url'), 'https://'))
            <meta property="og:image:secure_url" content="{{ str_replace('http://', 'https://', $ogImage) }}" />
        @endif
        <meta property="og:image:type" content="image/png" />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />
        <meta property="og:image:alt" content="{{ config('app.name') }} – {{ config('brand.tagline') }}" />

        {{--
            Ältere Vorschau-Dienste — darunter Microsofts SkypeUriPreview, das
            Teams benutzt — werten og:image nicht zuverlässig aus, kennen aber
            diese drei Formate von früher. Kosten nichts und schaden nirgends.
        --}}
        <link rel="image_src" href="{{ $ogImage }}" />
        <meta name="thumbnail" content="{{ $ogImage }}" />
        <meta itemprop="image" content="{{ $ogImage }}" />

        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="{{ $ogTitle ?? config('app.name') }}" />
        <meta name="twitter:description" content="{{ $ogDescription ?? config('brand.share_text') }}" />
        <meta name="twitter:image" content="{{ $ogImage }}" />

        @routes
        @vite(['resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="antialiased">
        {{--
            Ohne JavaScript ist der <body> vollständig leer: Inertia baut die
            Seite erst im Browser auf. Das trifft Menschen mit abgeschaltetem
            JavaScript ebenso wie Crawler, die keins ausführen.

            Nur auf der Startseite, und nur mit den Texten aus config/brand.php,
            die ohnehin schon Titel und Meta-Description füllen — so kann der
            Block nicht von dem abweichen, was Nutzer zu sehen bekommen.

            Inline-Stile, weil auch das CSS im JS-Bundle steckt.
        --}}
        @if (request()->routeIs('home'))
            <noscript>
                <div style="max-width:38rem;margin:0 auto;padding:3rem 1.5rem;font-family:system-ui,sans-serif;color:#14122B">
                    <h1 style="font-size:1.75rem;line-height:1.25;margin:0 0 .75rem">{{ config('brand.tagline') }}</h1>
                    <p style="margin:0 0 1.5rem;color:#6E6B85">{{ config('brand.claim') }}</p>
                    <p style="margin:0 0 1.5rem">{{ __('share.noscript') }}</p>
                    <p style="margin:0;font-size:.875rem">
                        <a href="/impressum" style="color:#5B4BE8">{{ __('Impressum') }}</a> ·
                        <a href="/datenschutz" style="color:#5B4BE8">{{ __('Datenschutz') }}</a>
                    </p>
                </div>
            </noscript>
        @endif

        @inertia
    </body>
</html>
