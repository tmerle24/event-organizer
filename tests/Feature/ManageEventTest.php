<?php

namespace Tests\Feature;

use App\Mail\EventCancelledMail;
use App\Mail\EventDecidedMail;
use App\Models\DateOption;
use App\Models\Event;
use App\Models\MailNotification;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ManageEventTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(): Event
    {
        $event = Event::create([
            'title' => 'Sommerfest',
            'mode' => Event::MODE_BOTH,
            'timezone' => 'Europe/Berlin',
            'status' => Event::STATUS_COLLECTING,
            'planning_template' => 'barbecue',
        ]);

        $event->dateOptions()->create([
            'starts_at_utc' => now()->addWeek()->setTime(16, 0),
            'day' => now()->addWeek()->toDateString(),
            'sort' => 0,
        ]);

        return $event;
    }

    public function test_the_manage_page_renders_for_the_manage_token(): void
    {
        $event = $this->makeEvent();

        $this->get("/e/{$event->manage_token}")->assertOk();
        $this->get('/e/'.str_repeat('z', 64))->assertNotFound();
    }

    public function test_the_organizer_can_edit_the_extracted_fields(): void
    {
        $event = $this->makeEvent();

        $this->patchJson("/e/{$event->manage_token}", [
            'title' => 'Sommerfest 2026',
            'location' => 'Im Garten',
            'participant_count_hint' => 12,
        ])->assertOk()->assertJsonPath('event.title', 'Sommerfest 2026');

        $this->assertSame('Im Garten', $event->fresh()->location);
    }

    public function test_confirming_a_date_notifies_participants_with_an_email_once(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $option = $event->dateOptions()->first();

        $event->participants()->create(['display_name' => 'Mit Mail', 'email' => 'a@test.de', 'token' => str_repeat('a', 32)]);
        $event->participants()->create(['display_name' => 'Ohne Mail', 'token' => str_repeat('b', 32)]);

        $this->postJson("/e/{$event->manage_token}/decide", ['date_option_id' => $option->id])
            ->assertOk()
            ->assertJsonPath('notified', 1);

        Mail::assertSent(EventDecidedMail::class, 1);

        $this->assertSame(Event::STATUS_DECIDED, $event->fresh()->status);
        $this->assertSame($option->id, $event->fresh()->decided_option_id);

        // Dedupe: ein zweiter Aufruf darf keine zweite Mail erzeugen.
        $this->postJson("/e/{$event->manage_token}/decide", ['date_option_id' => $option->id])
            ->assertJsonPath('notified', 0);

        Mail::assertSent(EventDecidedMail::class, 1);
        $this->assertSame(1, MailNotification::where('type', 'decided')->count());
    }

    public function test_confirming_a_date_creates_the_planning_sections(): void
    {
        Mail::fake();
        $event = $this->makeEvent();

        $this->postJson("/e/{$event->manage_token}/decide", [
            'date_option_id' => $event->dateOptions()->first()->id,
            'notify' => false,
        ])->assertOk();

        $this->assertGreaterThan(0, $event->planSections()->count());
    }

    public function test_cancelling_notifies_everyone_with_an_email(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $event->participants()->create(['display_name' => 'X', 'email' => 'x@test.de', 'token' => str_repeat('x', 32)]);

        $this->postJson("/e/{$event->manage_token}/cancel")->assertOk()->assertJsonPath('notified', 1);

        Mail::assertSent(EventCancelledMail::class, 1);
        $this->assertSame(Event::STATUS_CANCELLED, $event->fresh()->status);
    }

    public function test_deleting_an_option_that_was_confirmed_reopens_the_event(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $option = $event->dateOptions()->first();

        $this->postJson("/e/{$event->manage_token}/decide", ['date_option_id' => $option->id, 'notify' => false]);
        $this->deleteJson("/e/{$event->manage_token}/options/{$option->id}")->assertOk();

        $this->assertSame(Event::STATUS_COLLECTING, $event->fresh()->status);
        $this->assertNull($event->fresh()->decided_option_id);
    }

    public function test_generated_suggestions_do_not_duplicate_existing_options(): void
    {
        $event = $this->makeEvent();
        $day = now()->addDays(3);

        $payload = [
            'from' => $day->toDateString(),
            'to' => $day->copy()->addDays(2)->toDateString(),
            'time_of_day' => 'evening',
        ];

        $this->postJson("/e/{$event->manage_token}/options/suggest", $payload)->assertOk();
        $afterFirst = $event->dateOptions()->count();

        $this->postJson("/e/{$event->manage_token}/options/suggest", $payload)->assertOk();

        $this->assertSame($afterFirst, $event->dateOptions()->count());
    }

    public function test_deleting_the_event_removes_everything(): void
    {
        $event = $this->makeEvent();
        $event->participants()->create(['display_name' => 'Y', 'token' => str_repeat('y', 32)]);

        $this->deleteJson("/e/{$event->manage_token}")->assertOk();

        $this->assertSame(0, Event::count());
        $this->assertSame(0, Participant::count());
        $this->assertSame(0, DateOption::count());
    }

    public function test_the_ics_download_contains_the_confirmed_date(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $option = $event->dateOptions()->first();

        $this->postJson("/e/{$event->manage_token}/decide", ['date_option_id' => $option->id, 'notify' => false]);

        $response = $this->get("/t/{$event->public_token}/event.ics");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString('BEGIN:VEVENT', $response->getContent());
        $this->assertStringContainsString('SUMMARY:Sommerfest', $response->getContent());
        $this->assertStringContainsString($option->starts_at_utc->format('Ymd\THis\Z'), $response->getContent());
    }

    public function test_the_ics_download_is_unavailable_before_a_date_is_confirmed(): void
    {
        $event = $this->makeEvent();

        $this->get("/t/{$event->public_token}/event.ics")->assertNotFound();
    }
}
