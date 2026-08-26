<?php

namespace Tests\Feature;

use App\Models\Event;
use Carbon\CarbonImmutable;
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
        // Relative Pfade ignoriert der WhatsApp-Crawler kommentarlos. Bewusst
        // ohne Query-String — siehe Kommentar in app.blade.php.
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
     * Wer den Link bekommt, soll ohne Klick wissen, worum es geht und wann es
     * stattfindet. Das war zunächst anders gelöst — Event-Seiten zeigten die
     * generische Marken-Vorschau, damit Vorschau-Bots nichts verraten. Der
     * Nutzen für eine Einladung wiegt schwerer; der Link ist ohnehin der
     * Schlüssel zum Event.
     */
    public function test_a_shared_event_link_shows_title_and_date(): void
    {
        $event = Event::create([
            'title' => 'Team-BBQ',
            'mode' => Event::MODE_BOTH,
            'timezone' => 'Europe/Berlin',
            'status' => Event::STATUS_DECIDED,
            'location' => 'Im Garten',
        ]);

        $option = $event->dateOptions()->create([
            'starts_at_utc' => CarbonImmutable::create(2026, 9, 4, 16, 0, 0, 'UTC'),
            'day' => '2026-09-04',
            'sort' => 0,
        ]);
        $event->update(['decided_option_id' => $option->id]);

        $response = $this->get("/t/{$event->public_token}");

        $response->assertOk();
        $response->assertSee('property="og:title" content="Team-BBQ"', false);
        // 16:00 UTC sind 18:00 in Berlin — die Vorschau zeigt die Event-Zeitzone.
        $response->assertSee('18:00', false);
        $response->assertSee('Im Garten', false);
    }

    public function test_an_undecided_event_says_how_many_dates_are_up(): void
    {
        $event = Event::create([
            'title' => 'Kegelabend',
            'mode' => Event::MODE_DATES,
            'timezone' => 'Europe/Berlin',
            'status' => Event::STATUS_COLLECTING,
        ]);

        foreach ([3, 5] as $offset) {
            $event->dateOptions()->create([
                'starts_at_utc' => now()->addDays($offset),
                'day' => now()->addDays($offset)->toDateString(),
                'sort' => $offset,
            ]);
        }

        $response = $this->get("/t/{$event->public_token}");

        $response->assertOk();
        $response->assertSee('property="og:title" content="Kegelabend"', false);
        // Gegen die Übersetzung prüfen, nicht gegen deutschen Text: die
        // Testumgebung läuft nicht zwingend in derselben Sprache.
        $response->assertSee(trans_choice('share.collecting_with_options', 2, ['count' => 2]), false);
    }

    public function test_a_cancelled_event_says_so_in_the_preview(): void
    {
        $event = Event::create([
            'title' => 'Fällt aus',
            'timezone' => 'Europe/Berlin',
            'status' => Event::STATUS_CANCELLED,
        ]);

        $this->get("/t/{$event->public_token}")
            ->assertSee(__('share.cancelled'), false);
    }

    /**
     * Der Verwaltungslink gehört nicht in einen Gruppenchat — die Manage-Seite
     * bleibt deshalb bei der generischen Vorschau.
     */
    public function test_the_manage_page_keeps_the_generic_preview(): void
    {
        $event = Event::create([
            'title' => 'Geheime Überraschungsparty',
            'timezone' => 'Europe/Berlin',
            'status' => Event::STATUS_COLLECTING,
        ]);

        $response = $this->get("/e/{$event->manage_token}");

        $response->assertOk();
        $response->assertSee('property="og:title" content="'.config('app.name').'"', false);
        $response->assertDontSee('Geheime Überraschungsparty', false);
        $response->assertSee('name="robots" content="noindex"', false);
    }
}
