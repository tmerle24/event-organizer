<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_event_from_free_text_without_an_account(): void
    {
        $response = $this->postJson('/events', [
            'input' => 'Team BBQ mit 8 Leuten im September, Freitag oder Samstag abends',
            'mode' => 'both',
            'timezone' => 'Europe/Berlin',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['manage_token', 'public_token', 'manage_url', 'needs_date_range']);

        $event = Event::firstOrFail();

        $this->assertSame(64, strlen($event->manage_token));
        $this->assertSame(12, strlen($event->public_token));
        $this->assertSame('barbecue', $event->event_type);
        $this->assertSame(8, $event->participant_count_hint);
        $this->assertSame(Event::STATUS_COLLECTING, $event->status);
        $this->assertNotNull($event->delete_after);
    }

    public function test_it_suggests_date_options_from_the_extracted_range(): void
    {
        $this->postJson('/events', [
            'input' => 'Grillabend im September, nur Freitag oder Samstag',
            'timezone' => 'Europe/Berlin',
        ])->assertCreated();

        $event = Event::firstOrFail();

        $this->assertGreaterThan(0, $event->dateOptions()->count());
        $this->assertLessThanOrEqual(6, $event->dateOptions()->count());

        foreach ($event->dateOptions as $option) {
            $weekday = $option->starts_at_utc->setTimezone('Europe/Berlin')->dayOfWeek;
            $this->assertContains($weekday, [5, 6], 'Nur die gewuenschten Wochentage werden vorgeschlagen.');
        }
    }

    public function test_it_creates_no_options_when_no_time_frame_is_given(): void
    {
        $this->postJson('/events', ['input' => 'Kegelabend organisieren'])
            ->assertCreated()
            ->assertJsonPath('needs_date_range', true);

        $this->assertSame(0, Event::firstOrFail()->dateOptions()->count());
    }

    public function test_a_list_only_event_starts_in_planning_with_sections(): void
    {
        $this->postJson('/events', ['input' => 'Grillfest Orga', 'mode' => 'list'])->assertCreated();

        $event = Event::firstOrFail();

        $this->assertSame(Event::STATUS_PLANNING, $event->status);
        $this->assertGreaterThan(0, $event->planSections()->count());
    }

    public function test_the_honeypot_rejects_bots(): void
    {
        $this->postJson('/events', ['input' => 'BBQ', 'website' => 'http://spam.test'])
            ->assertStatus(422);

        $this->assertSame(0, Event::count());
    }

    public function test_input_is_limited_to_500_characters(): void
    {
        $this->postJson('/events', ['input' => str_repeat('a', 501)])
            ->assertStatus(422);
    }
}
