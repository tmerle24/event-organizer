<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Eine Organisationsliste hat keine Terminfindung — der Termin kann aber
 * trotzdem schon feststehen. Er wird als einzelne, direkt bestätigte
 * Terminoption abgelegt, damit iCal-Download und Link-Vorschau ohne
 * Sonderweg greifen.
 */
class FixedDateTest extends TestCase
{
    use RefreshDatabase;

    private function listEvent(): Event
    {
        $this->postJson('/events', [
            'input' => 'Team-BBQ, wer bringt was mit',
            'mode' => 'list',
            'timezone' => 'Europe/Berlin',
            'fixed_date' => '2026-08-29',
            'fixed_time' => '14:00',
        ])->assertCreated();

        return Event::firstOrFail();
    }

    public function test_a_list_can_be_created_with_a_date(): void
    {
        $event = $this->listEvent();

        $this->assertSame(1, $event->dateOptions()->count());
        $this->assertNotNull($event->decided_option_id);

        $option = $event->decidedOption;
        $this->assertFalse($option->all_day);
        $this->assertSame(
            '2026-08-29 14:00',
            $option->starts_at_utc->setTimezone('Europe/Berlin')->format('Y-m-d H:i')
        );
    }

    public function test_the_date_is_optional(): void
    {
        $this->postJson('/events', ['input' => 'Nur eine Liste', 'mode' => 'list'])->assertCreated();

        $event = Event::firstOrFail();

        $this->assertSame(0, $event->dateOptions()->count());
        $this->assertNull($event->decided_option_id);
    }

    public function test_without_a_time_the_date_is_all_day(): void
    {
        $this->postJson('/events', [
            'input' => 'Ausflug',
            'mode' => 'list',
            'fixed_date' => '2026-08-29',
        ])->assertCreated();

        $this->assertTrue(Event::firstOrFail()->decidedOption->all_day);
    }

    public function test_the_organizer_can_change_and_remove_the_date(): void
    {
        $event = $this->listEvent();

        $this->patchJson("/e/{$event->manage_token}", [
            'fixed_date' => '2026-09-05',
            'fixed_time' => '18:30',
        ])->assertOk();

        $this->assertSame(
            '2026-09-05 18:30',
            $event->fresh()->decidedOption->starts_at_utc->setTimezone('Europe/Berlin')->format('Y-m-d H:i')
        );
        $this->assertSame(1, $event->fresh()->dateOptions()->count(), 'Kein zweiter Termin bleibt liegen.');

        $this->patchJson("/e/{$event->manage_token}", ['fixed_date' => null])->assertOk();

        $this->assertNull($event->fresh()->decided_option_id);
        $this->assertSame(0, $event->fresh()->dateOptions()->count());
    }

    /**
     * Bei einem Event mit Terminfindung würde ein fester Termin die
     * abgestimmten Optionen löschen — deshalb greift er dort nicht.
     */
    public function test_an_event_with_date_polling_is_not_affected(): void
    {
        $this->postJson('/events', [
            'input' => 'Grillen im September, Freitag oder Samstag',
            'mode' => 'dates',
            'timezone' => 'Europe/Berlin',
        ])->assertCreated();

        $event = Event::firstOrFail();
        $before = $event->dateOptions()->count();
        $this->assertGreaterThan(1, $before);

        $this->patchJson("/e/{$event->manage_token}", ['fixed_date' => '2026-08-29'])->assertOk();

        $this->assertSame($before, $event->fresh()->dateOptions()->count());
        $this->assertNull($event->fresh()->decided_option_id);
    }

    public function test_the_date_reaches_calendar_download_and_link_preview(): void
    {
        $event = $this->listEvent();

        $ics = $this->get("/t/{$event->public_token}/event.ics");
        $ics->assertOk();
        $this->assertStringContainsString('DTSTART:20260829T120000Z', $ics->getContent());

        // 14:00 Berlin sind 12:00 UTC — die Vorschau zeigt die Event-Zeitzone.
        $this->get("/t/{$event->public_token}")->assertSee('14:00', false);
    }
}
