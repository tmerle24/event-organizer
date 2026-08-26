<?php

namespace App\Services;

use App\Mail\EventCancelledMail;
use App\Mail\EventDecidedMail;
use App\Mail\EventInviteMail;
use App\Mail\ManageLinkMail;
use App\Models\Event;
use App\Models\MailNotification;
use App\Models\Participant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Spec Abschnitt 8: nur E-Mail, nur transaktional, Dedupe-Key pro
 * (Empfaenger, Typ, Event), damit Retries keine Doppelmails erzeugen.
 */
class EventNotifier
{
    public const TYPE_INVITE = 'invite';

    public const TYPE_DECIDED = 'decided';

    public const TYPE_CANCELLED = 'cancelled';

    public const TYPE_MANAGE_LINK = 'manage_link';

    public function invite(Event $event, string $email, ?string $name = null): bool
    {
        return $this->send($event, $email, self::TYPE_INVITE, fn () => new EventInviteMail($event, $name));
    }

    /**
     * Bestaetigter Termin geht an alle Teilnehmer mit hinterlegter E-Mail.
     */
    public function announceDecision(Event $event): int
    {
        return $this->fanOut($event, self::TYPE_DECIDED, fn (Participant $p) => new EventDecidedMail($event, $p));
    }

    public function announceCancellation(Event $event): int
    {
        return $this->fanOut($event, self::TYPE_CANCELLED, fn (Participant $p) => new EventCancelledMail($event, $p));
    }

    public function sendManageLink(Event $event, string $email): bool
    {
        // Der Verwaltungslink darf mehrfach anforderbar sein (Geraetewechsel),
        // deshalb bekommt er einen zeitbasierten Dedupe-Key statt eines festen.
        return $this->send(
            $event,
            $email,
            self::TYPE_MANAGE_LINK,
            fn () => new ManageLinkMail($event),
            self::TYPE_MANAGE_LINK.':'.$event->id.':'.$email.':'.now()->timestamp
        );
    }

    private function fanOut(Event $event, string $type, callable $factory): int
    {
        $sent = 0;

        foreach ($event->participants()->whereNotNull('email')->get() as $participant) {
            if ($this->send($event, $participant->email, $type, fn () => $factory($participant))) {
                $sent++;
            }
        }

        return $sent;
    }

    private function send(Event $event, string $email, string $type, callable $factory, ?string $dedupeKey = null): bool
    {
        $dedupeKey ??= $type.':'.$event->id.':'.mb_strtolower($email);

        if (MailNotification::where('dedupe_key', $dedupeKey)->exists()) {
            return false;
        }

        $record = MailNotification::create([
            'event_id' => $event->id,
            'recipient_email' => $email,
            'type' => $type,
            'dedupe_key' => $dedupeKey,
        ]);

        try {
            Mail::to($email)->send($factory());
            $record->update(['sent_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Mail failed', ['type' => $type, 'event' => $event->id, 'error' => $e->getMessage()]);
            $record->update(['error' => mb_substr($e->getMessage(), 0, 1000)]);

            return false;
        }
    }
}
