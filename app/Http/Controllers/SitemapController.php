<?php

namespace App\Http\Controllers;

/**
 * Sitemap mit den drei öffentlichen Seiten.
 *
 * Event-Seiten stehen bewusst nicht drin: sie sind privat, tragen noindex und
 * würden hier ihre Tokens preisgeben — eine Sitemap ist öffentlich lesbar.
 *
 * Bewusst eine Route statt einer Datei unter public/, damit die Adressen aus
 * APP_URL kommen und nicht fest verdrahtet sind.
 */
class SitemapController extends Controller
{
    public function show()
    {
        $urls = [
            route('home'),
            route('legal.imprint'),
            route('legal.privacy'),
        ];

        $body = implode("\n", array_map(
            fn ($url) => '  <url><loc>'.htmlspecialchars($url, ENT_XML1).'</loc></url>',
            $urls
        ));

        $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
        $body
        </urlset>

        XML;

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
