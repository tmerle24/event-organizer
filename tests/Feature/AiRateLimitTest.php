<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Spec Abschnitt 11: Grenzen je IP für den KI-Aufruf.
 *
 * Entscheidend ist, dass eine Überschreitung nichts kaputt macht — sie führt
 * zur Heuristik, nicht zu einem Fehler. Wer die Grenze reißt, legt sein Event
 * ganz normal an und merkt nichts davon.
 */
class AiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.key' => 'test-key',
            'ai.per_ip_hourly' => 3,
            'ai.per_ip_daily' => 5,
            'ai.daily_budget' => 500,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'input' => ['event_name' => 'Aus der KI', 'event_type' => 'barbecue'],
                ]],
            ]),
        ]);
    }

    private function create(string $ip): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/events', ['input' => 'Team-BBQ im September', 'mode' => 'dates']);
    }

    public function test_requests_within_the_limit_use_the_ai(): void
    {
        $this->create('10.0.0.1')->assertCreated();

        $this->assertSame('ai', Event::firstOrFail()->ai_meta['source']);
        $this->assertSame('Aus der KI', Event::firstOrFail()->title);
        Http::assertSentCount(1);
    }

    public function test_beyond_the_limit_the_event_is_still_created_without_the_user_noticing(): void
    {
        foreach (range(1, 3) as $i) {
            $this->create('10.0.0.2')->assertCreated();
        }

        Http::assertSentCount(3);

        // Die vierte Anfrage überschreitet die Stundengrenze.
        $this->create('10.0.0.2')->assertCreated();

        Http::assertSentCount(3);

        $last = Event::latest('id')->first();
        $this->assertSame('heuristic', $last->ai_meta['source']);
        $this->assertNotSame('Aus der KI', $last->title);
    }

    public function test_the_limit_is_per_ip(): void
    {
        foreach (range(1, 3) as $i) {
            $this->create('10.0.0.3')->assertCreated();
        }

        $this->create('10.0.0.4')->assertCreated();

        Http::assertSentCount(4);
        $this->assertSame('ai', Event::latest('id')->first()->ai_meta['source']);
    }

    public function test_the_daily_limit_also_applies(): void
    {
        config(['ai.per_ip_hourly' => 99, 'ai.per_ip_daily' => 2]);

        foreach (range(1, 2) as $i) {
            $this->create('10.0.0.5')->assertCreated();
        }

        $this->create('10.0.0.5')->assertCreated();

        Http::assertSentCount(2);
        $this->assertSame('heuristic', Event::latest('id')->first()->ai_meta['source']);
    }

    public function test_the_global_budget_stays_a_hard_cutoff(): void
    {
        config(['ai.daily_budget' => 1]);

        $this->create('10.0.0.6')->assertCreated();
        $this->create('10.0.0.7')->assertCreated();

        Http::assertSentCount(1);
        $this->assertSame('heuristic', Event::latest('id')->first()->ai_meta['source']);
    }
}
