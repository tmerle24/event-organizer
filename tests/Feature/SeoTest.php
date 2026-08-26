<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sitemap_lists_the_public_pages(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        foreach ([route('home'), route('legal.imprint'), route('legal.privacy')] as $url) {
            $response->assertSee("<loc>$url</loc>", false);
        }
    }

    /**
     * Eine Sitemap ist öffentlich lesbar — ein Event-Token darin wäre der
     * Schlüssel zum Event, für jeden einsehbar.
     */
    public function test_the_sitemap_never_contains_event_urls(): void
    {
        $event = Event::create(['title' => 'Team-BBQ', 'timezone' => 'Europe/Berlin']);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee($event->public_token, false);
        $response->assertDontSee($event->manage_token, false);
        $response->assertDontSee('/t/', false);
        $response->assertDontSee('/e/', false);
    }

    public function test_the_verification_tag_appears_only_when_configured(): void
    {
        config(['brand.google_site_verification' => null]);
        $this->get('/')->assertDontSee('google-site-verification', false);

        config(['brand.google_site_verification' => 'abc123']);
        $this->get('/')->assertSee('<meta name="google-site-verification" content="abc123" />', false);
    }
}
