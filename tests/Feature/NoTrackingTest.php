<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Der Footer verspricht "ohne Tracking". Das stimmt nur, solange keine Seite
 * etwas von fremden Servern nachlädt - eine Schrift von Google reicht schon,
 * damit jede IP dort landet.
 */
class NoTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_load_nothing_from_third_party_hosts(): void
    {
        $event = Event::create(['title' => 'Team-BBQ', 'timezone' => 'Europe/Berlin']);

        foreach (['/', '/datenschutz', '/impressum', "/t/{$event->public_token}", "/e/{$event->manage_token}"] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            // Nur was der Browser selbst lädt: script src, link href (ohne canonical), img src
            preg_match_all('/<(?:script|img)\b[^>]*\bsrc="([^"]+)"|<link\b(?![^>]*rel="canonical")[^>]*\bhref="([^"]+)"/i', $html, $m);

            foreach (array_filter(array_merge($m[1], $m[2])) as $url) {
                $this->assertTrue(
                    $this->isOwn($url),
                    "$path lädt $url von einem fremden Server."
                );
            }
        }
    }

    public function test_sources_reference_no_third_party_resources(): void
    {
        $files = array_merge(
            glob(resource_path('css/*.css')),
            glob(resource_path('views/*.blade.php')),
        );

        foreach ($files as $file) {
            $content = file_get_contents($file);

            // @import url(...), url(...) in CSS
            preg_match_all('/(?:@import\s+(?:url\()?|url\()\s*[\'"]?(https?:)?\/\/([^\'")\s]+)/i', $content, $m);

            $this->assertSame([], $m[0], basename($file).' bindet eine externe Ressource ein.');
        }

        $this->assertDoesNotMatchRegularExpression(
            '/fonts\.(googleapis|gstatic)\.com/',
            implode("\n", array_map('file_get_contents', $files)),
            'Google Fonts ist wieder eingebunden.'
        );
    }

    private function isOwn(string $url): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host === parse_url(config('app.url'), PHP_URL_HOST)
            || in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
    }
}
