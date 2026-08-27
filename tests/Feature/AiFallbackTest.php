<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Spec Abschnitt 7: "Die KI ist Beschleuniger, nie Voraussetzung."
 *
 * Jeder Fehlerpfad muss in der Heuristik enden und darf für die Person, die
 * gerade ein Event anlegt, unsichtbar bleiben. Deshalb prüft jeder Test hier
 * beides: dass das Event entsteht und dass die Heuristik gegriffen hat.
 */
class AiFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.key' => 'test-key']);
    }

    private function create(): TestResponse
    {
        return $this->postJson('/events', [
            'input' => 'Team-BBQ mit 8 Leuten im September, Freitag oder Samstag',
            'mode' => 'dates',
            'timezone' => 'Europe/Berlin',
        ]);
    }

    private function assertFellBackSilently(): void
    {
        $event = Event::firstOrFail();

        $this->assertSame('heuristic', $event->ai_meta['source']);
        // Die Heuristik hat trotzdem gearbeitet: Typ, Anzahl und Termine stehen.
        $this->assertSame('barbecue', $event->event_type);
        $this->assertSame(8, $event->participant_count_hint);
        $this->assertGreaterThan(0, $event->dateOptions()->count());
    }

    public function test_without_a_key_nothing_is_called(): void
    {
        config(['ai.key' => null]);
        Http::fake();

        $this->create()->assertCreated();

        Http::assertNothingSent();
        $this->assertFellBackSilently();
    }

    public function test_a_server_error_is_invisible(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response('nope', 500)]);

        $this->create()->assertCreated();

        $this->assertFellBackSilently();
    }

    public function test_a_rate_limit_from_the_provider_is_invisible(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'rate_limited'], 429)]);

        $this->create()->assertCreated();

        $this->assertFellBackSilently();
    }

    public function test_a_timeout_is_invisible(): void
    {
        Http::fake(fn () => throw new ConnectionException('Timeout'));

        $this->create()->assertCreated();

        $this->assertFellBackSilently();
    }

    public function test_a_broken_answer_is_invisible(): void
    {
        // Antwort ohne tool_use-Block: Schema nicht eingehalten.
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'hi']]])]);

        $this->create()->assertCreated();

        $this->assertFellBackSilently();
    }
}
