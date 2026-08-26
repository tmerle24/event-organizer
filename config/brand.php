<?php

/**
 * ORGDATE Brand Guide v1.0. Der Name selbst steht in APP_NAME; hier stehen die
 * Textbausteine, die ausserhalb von vue-i18n gebraucht werden (Meta-Tags,
 * Mail-Footer). Tonalitaet nach Abschnitt 6: locker, entlastend, geduzt.
 */
return [
    'tagline' => 'Termin finden. Plan machen. Fertig.',

    // Meta-Description der Seite.
    'claim' => 'Ohne Anmeldung einen Termin finden, der allen passt – und danach verteilen, wer was mitbringt.',

    // Text der Link-Vorschau. Deckungsgleich mit der Zeile im OG-Bild, damit
    // Bild und Text in WhatsApp & Co. dasselbe sagen.
    'share_text' => 'Der Termin, der allen passt. Gemeinsam planen, ohne Hin und Her.',
    'domain' => env('BRAND_DOMAIN', 'orgdate.com'),

    /*
     * Bestätigungscode der Google Search Console. Nur der Wert aus dem
     * content-Attribut, nicht das ganze Meta-Tag. Leer = kein Tag im HTML.
     */
    'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    'email' => env('BRAND_EMAIL', 'hello@orgdate.com'),
];
