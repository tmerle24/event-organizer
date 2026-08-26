<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Die Link-Vorschau ist der erste Kontakt mit dem Produkt — sie bricht still,
 * wenn eine der Bedingungen aus brand/og/README.md verletzt wird. Deshalb
 * stehen sie hier als Test statt nur in der Doku.
 */
class ShareImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_carries_a_complete_link_preview(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        foreach ([
            'property="og:title"',
            'property="og:description"',
            'property="og:image"',
            'property="og:image:width" content="1200"',
            'property="og:image:height" content="630"',
            'property="og:image:type" content="image/png"',
            'property="og:image:alt"',
            'property="og:locale"',
            'name="twitter:card" content="summary_large_image"',
        ] as $needle) {
            $response->assertSee($needle, false);
        }
    }

    public function test_the_image_url_is_absolute(): void
    {
        // Relative Pfade ignoriert der WhatsApp-Crawler kommentarlos.
        $this->get('/')->assertSee(
            'property="og:image" content="'.config('app.url').'/og/og-image.png"',
            false
        );
    }

    public function test_the_share_image_exists_and_stays_under_the_size_limit(): void
    {
        foreach (['og-image', 'og-image-square', 'og-image-light', 'og-image-dark'] as $name) {
            $path = public_path("og/$name.png");

            $this->assertFileExists($path);
            $this->assertLessThan(
                600 * 1024,
                filesize($path),
                "$name.png ist größer als 600 KB — WhatsApp lädt es dann nicht."
            );
        }

        [$width, $height] = getimagesize(public_path('og/og-image.png'));
        $this->assertSame([1200, 630], [$width, $height]);

        [$width, $height] = getimagesize(public_path('og/og-image-square.png'));
        $this->assertSame([1200, 1200], [$width, $height]);
    }

    /**
     * Event-Seiten zeigen bewusst die generische Marken-Vorschau: Vorschau-Bots
     * holen die Seite, ohne dass die empfangende Person das ausgelöst hat.
     */
    public function test_event_pages_do_not_leak_their_title_into_the_preview(): void
    {
        $event = Event::create([
            'title' => 'Geheime Überraschungsparty',
            'timezone' => 'Europe/Berlin',
            'status' => Event::STATUS_COLLECTING,
        ]);

        $response = $this->get("/t/{$event->public_token}");

        $response->assertOk();
        $response->assertSee('property="og:title" content="'.config('app.name').'"', false);
        $response->assertDontSee('property="og:title" content="Geheime Überraschungsparty"', false);
        $response->assertSee('name="robots" content="noindex, nofollow"', false);
    }
}
