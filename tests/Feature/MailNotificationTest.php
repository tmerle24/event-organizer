<?php

namespace Tests\Feature;

use App\Mail\EventCancelledMail;
use App\Mail\EventChangedMail;
use App\Mail\EventDecidedMail;
use App\Mail\EventInviteMail;
use App\Mail\EventReopenedMail;
use App\Models\Event;
use App\Models\MailNotification;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(int $options = 2): Event
    {
        $event = Event::create([
            'title' => 'Sommerfest',
            'mode' => Event::MODE_BOTH,
            'timezone' => 'Europe/Berlin',
            'status' => Event::STATUS_COLLECTING,
        ]);

        for ($i = 0; $i < $options; $i++) {
            $event->dateOptions()->create([
                'starts_at_utc' => now()->addWeek()->addDays($i)->setTime(16, 0),
                'day' => now()->addWeek()->addDays($i)->toDateString(),
                'sort' => $i,
            ]);
        }

        return $event;
    }

    private function withGuest(Event $event, string $email = 'anna@example.org'): Participant
    {
        return $event->participants()->create([
            'display_name' => 'Anna',
            'email' => $email,
            'token' => str_repeat('a', 32),
        ]);
    }

    private function decide(Event $event, int $optionId): void
    {
        $this->postJson("/e/{$event->manage_token}/decide", ['date_option_id' => $optionId])->assertOk();
    }

    private function unsubscribeUrl(Event $event, Participant $participant): string
    {
        return URL::signedRoute('public.unsubscribe', [
            'event' => $event->public_token,
            'participant' => $participant->id,
        ], absolute: false);
    }

    // --- Adressen im Protokoll ---------------------------------------------

    public function test_invitation_addresses_are_not_stored_anywhere(): void
    {
        Mail::fake();
        $event = $this->makeEvent();

        $this->postJson("/e/{$event->manage_token}/invite", ['emails' => ['Gast@Example.org']])
            ->assertOk()->assertJsonPath('sent', 1);

        Mail::assertSent(EventInviteMail::class, fn ($mail) => $mail->hasTo('Gast@Example.org'));

        foreach (['mail_notifications', 'participants', 'events', 'jobs', 'failed_jobs'] as $table) {
            $dump = mb_strtolower(json_encode(DB::table($table)->get()));
            $this->assertStringNotContainsString('gast@example.org', $dump, "Adresse in {$table}");
        }
    }

    public function test_the_same_invitation_is_not_sent_twice(): void
    {
        Mail::fake();
        $event = $this->makeEvent();

        $this->postJson("/e/{$event->manage_token}/invite", ['emails' => ['gast@example.org']]);
        $this->postJson("/e/{$event->manage_token}/invite", ['emails' => ['GAST@example.org']])
            ->assertJsonPath('sent', 0);

        Mail::assertSent(EventInviteMail::class, 1);
    }

    public function test_participant_mails_reference_the_participant_not_the_address(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $anna = $this->withGuest($event);

        $this->decide($event, $event->dateOptions()->first()->id);

        $record = MailNotification::sole();
        $this->assertSame($anna->id, $record->participant_id);
        $this->assertNull($record->recipient_hash);
        $this->assertStringNotContainsString('anna@example.org', json_encode($record->getAttributes()));
    }

    public function test_failed_deliveries_do_not_store_the_address_from_the_error(): void
    {
        $event = $this->makeEvent();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('550 5.1.1 <gast@example.org>: Recipient address rejected'));

        $this->postJson("/e/{$event->manage_token}/invite", ['emails' => ['gast@example.org']])
            ->assertJsonPath('sent', 0);

        $record = MailNotification::sole();
        $this->assertStringContainsString('[address]', $record->error);
        $this->assertStringNotContainsString('gast@example.org', json_encode($record->getAttributes()));
    }

    // --- Updates ------------------------------------------------------------

    public function test_a_newly_confirmed_date_is_announced_again(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $this->withGuest($event);
        [$friday, $saturday] = $event->dateOptions()->orderBy('sort')->pluck('id')->all();

        $this->decide($event, $friday);
        $this->postJson("/e/{$event->manage_token}/undecide")->assertOk();
        $this->decide($event, $saturday);

        Mail::assertSent(EventDecidedMail::class, 2);

        // zurueck auf Freitag ist wieder eine neue Info
        $this->postJson("/e/{$event->manage_token}/undecide");
        $this->decide($event, $friday);

        Mail::assertSent(EventDecidedMail::class, 3);
    }

    public function test_confirming_the_same_date_again_sends_nothing(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $this->withGuest($event);
        $friday = $event->dateOptions()->first()->id;

        $this->decide($event, $friday);
        $this->postJson("/e/{$event->manage_token}/undecide");
        $this->decide($event, $friday);

        Mail::assertSent(EventDecidedMail::class, 1);
    }

    public function test_cancel_reopen_cancel_announces_every_step(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $this->withGuest($event);

        $this->postJson("/e/{$event->manage_token}/cancel")->assertJsonPath('notified', 1);
        $this->postJson("/e/{$event->manage_token}/cancel")->assertJsonPath('notified', 0);
        $this->postJson("/e/{$event->manage_token}/reopen")->assertJsonPath('notified', 1);
        $this->postJson("/e/{$event->manage_token}/cancel")->assertJsonPath('notified', 1);

        Mail::assertSent(EventCancelledMail::class, 2);
        Mail::assertSent(EventReopenedMail::class, 1);
    }

    public function test_reopening_only_reaches_people_who_got_the_cancellation(): void
    {
        Mail::fake();
        $event = $this->makeEvent();

        $this->postJson("/e/{$event->manage_token}/cancel");
        $this->withGuest($event);
        $this->postJson("/e/{$event->manage_token}/reopen")->assertJsonPath('notified', 0);

        Mail::assertNotSent(EventReopenedMail::class);
    }

    public function test_a_changed_location_after_confirmation_is_announced(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $this->withGuest($event);

        // vor der Festlegung: keine Mail
        $this->patchJson("/e/{$event->manage_token}", ['location' => 'Im Park'])->assertOk();
        Mail::assertNotSent(EventChangedMail::class);

        $this->decide($event, $event->dateOptions()->first()->id);
        $this->patchJson("/e/{$event->manage_token}", ['location' => 'Im Garten'])->assertOk();
        // gleicher Wert nochmal: nichts
        $this->patchJson("/e/{$event->manage_token}", ['location' => 'Im Garten'])->assertOk();
        // Titel ist kein Update wert
        $this->patchJson("/e/{$event->manage_token}", ['title' => 'Sommerfest 2026'])->assertOk();

        Mail::assertSent(EventChangedMail::class, 1);
    }

    public function test_a_changed_time_of_the_confirmed_date_is_announced(): void
    {
        Mail::fake();
        $event = $this->makeEvent();
        $this->withGuest($event);
        [$confirmed, $other] = $event->dateOptions()->orderBy('sort')->get()->all();

        $this->decide($event, $confirmed->id);
        $this->patchJson("/e/{$event->manage_token}/options/{$other->id}", ['time' => '12:00'])->assertOk();
        Mail::assertNotSent(EventChangedMail::class);

        $this->patchJson("/e/{$event->manage_token}/options/{$confirmed->id}", ['time' => '19:30'])->assertOk();
        Mail::assertSent(EventChangedMail::class, 1);
    }

    public function test_changes_do_not_reach_people_who_never_got_the_date(): void
    {
        Mail::fake();
        $event = $this->makeEvent();

        $this->decide($event, $event->dateOptions()->first()->id);
        $this->withGuest($event);
        $this->patchJson("/e/{$event->manage_token}", ['location' => 'Im Garten'])->assertOk();

        Mail::assertNotSent(EventChangedMail::class);
    }

    // --- Abmelden -----------------------------------------------------------

    public function test_participant_mails_carry_an_unsubscribe_link(): void
    {
        $event = $this->makeEvent();
        $anna = $this->withGuest($event);
        $this->decide($event, $event->dateOptions()->first()->id);

        $mail = new EventDecidedMail($event->fresh(), $anna);
        $url = $mail->unsubscribeUrl();

        $this->assertStringContainsString("/t/{$event->public_token}/unsubscribe/{$anna->id}", $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertSame('<'.$url.'>', $mail->headers()->text['List-Unsubscribe']);
        $this->assertStringContainsString(e($url), $mail->render());
    }

    public function test_opening_the_unsubscribe_link_changes_nothing(): void
    {
        $event = $this->makeEvent();
        $anna = $this->withGuest($event);

        $this->get($this->unsubscribeUrl($event, $anna))->assertOk();

        $this->assertSame('anna@example.org', $anna->fresh()->email);
    }

    public function test_confirming_removes_only_the_address(): void
    {
        $event = $this->makeEvent();
        $anna = $this->withGuest($event);
        $anna->availabilities()->create(['date_option_id' => $event->dateOptions()->first()->id, 'value' => 'yes']);

        // One-Click aus dem Mailprogramm: ohne CSRF-Token
        $this->post($this->unsubscribeUrl($event, $anna), ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $anna->refresh();
        $this->assertNull($anna->email);
        $this->assertSame('Anna', $anna->display_name);
        $this->assertSame(1, $anna->availabilities()->count());
    }

    public function test_the_unsubscribe_link_cannot_be_forged(): void
    {
        $event = $this->makeEvent();
        $anna = $this->withGuest($event);
        $other = $event->participants()->create(['display_name' => 'Ben', 'email' => 'ben@example.org', 'token' => str_repeat('b', 32)]);

        // Signatur von Anna, ID von Ben
        $forged = str_replace("/unsubscribe/{$anna->id}", "/unsubscribe/{$other->id}", $this->unsubscribeUrl($event, $anna));

        $this->post($forged)->assertForbidden();
        $this->post("/t/{$event->public_token}/unsubscribe/{$other->id}")->assertForbidden();
        $this->assertSame('ben@example.org', $other->fresh()->email);
    }

    public function test_a_participant_of_another_event_cannot_be_unsubscribed(): void
    {
        $event = $this->makeEvent();
        $foreign = $this->withGuest($this->makeEvent());

        $url = URL::signedRoute('public.unsubscribe', [
            'event' => $event->public_token,
            'participant' => $foreign->id,
        ], absolute: false);

        $this->post($url)->assertNotFound();
        $this->assertNotNull($foreign->fresh()->email);
    }

    // --- Verwaltungsseite ---------------------------------------------------

    public function test_the_manage_data_never_contains_participant_addresses(): void
    {
        $event = $this->makeEvent();
        $this->withGuest($event);

        $response = $this->getJson("/e/{$event->manage_token}/data")->assertOk();

        $response->assertJsonPath('event.participants.0.has_email', true);
        $this->assertStringNotContainsString('anna@example.org', $response->getContent());
        $this->assertStringNotContainsString('anna@example.org', $this->get("/e/{$event->manage_token}")->getContent());
    }

    public function test_the_organizer_cannot_set_a_participant_address(): void
    {
        $event = $this->makeEvent();
        $ben = $event->participants()->create(['display_name' => 'Ben', 'token' => str_repeat('b', 32)]);

        $this->patchJson("/e/{$event->manage_token}/participants/{$ben->id}", ['email' => 'ben@example.org'])->assertOk();

        $this->assertNull($ben->fresh()->email);
    }
}
