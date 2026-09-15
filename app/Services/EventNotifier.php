<?php

namespace App\Services;

use App\Mail\EventCancelledMail;
use App\Mail\EventChangedMail;
use App\Mail\EventDecidedMail;
use App\Mail\EventInviteMail;
use App\Mail\EventReopenedMail;
use App\Mail\ManageLinkMail;
use App\Models\Event;
use App\Models\MailNotification;
use App\Models\Participant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Spec Abschnitt 8: nur E-Mail, nur transaktional.
 *
 * Das Versandprotokoll enthaelt nie eine Adresse: Teilnehmer-Mails verweisen
 * auf participant_id, Einladung und Verwaltungslink nur auf einen HMAC-Hash.
 * Die Adresse existiert damit genau einmal — beim Teilnehmer bzw. Event.
 *
 * Dedupe fuer Teilnehmer-Mails laeuft ueber einen Fingerprint des Inhalts:
 * gleiche Info wie in der letzten Mail derselben Familie → keine neue Mail.
 * Der Key enthaelt die ID der Vorgaenger-Mail, damit doppelte Requests an der
 * Unique-Constraint scheitern, ein spaeterer Wechsel A → B → A aber durchgeht.
 */
class EventNotifier
{
    public const TYPE_INVITE = 'invite';

    public const TYPE_DECIDED = 'decided';

    public const TYPE_CHANGED = 'changed';

    public const TYPE_CANCELLED = 'cancelled';

    public const TYPE_REOPENED = 'reopened';

    public const TYPE_MANAGE_LINK = 'manage_link';

    /** Termin & Ort */
    private const FAMILY_SCHEDULE = [self::TYPE_DECIDED, self::TYPE_CHANGED];

    /** Absage & Zuruecknahme */
    private const FAMILY_STATUS = [self::TYPE_CANCELLED, self::TYPE_REOPENED];

    public static function hashAddress(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), config('app.key'));
    }

    /**
     * Einladungsadressen werden nur fuer den Versand benutzt, nie gespeichert.
     */
    public function invite(Event $event, string $email): bool
    {
        $hash = self::hashAddress($email);

        return $this->deliver(
            $event,
            $email,
            fn () => new EventInviteMail($event),
            ['type' => self::TYPE_INVITE, 'recipient_hash' => $hash, 'dedupe_key' => self::TYPE_INVITE.':'.$event->id.':'.$hash],
        );
    }

    public function announceDecision(Event $event): int
    {
        return $this->fanOut(
            $event,
            self::TYPE_DECIDED,
            self::FAMILY_SCHEDULE,
            $this->scheduleFingerprint($event),
            fn (Participant $p) => new EventDecidedMail($event, $p),
        );
    }

    /**
     * Termin oder Ort nach der Festlegung geaendert. Nur an Teilnehmer, die den
     * alten Stand per Mail bekommen haben — wer nie informiert wurde, braucht
     * kein Update (und notify=false beim Festlegen bleibt respektiert).
     */
    public function announceChange(Event $event): int
    {
        return $this->fanOut(
            $event,
            self::TYPE_CHANGED,
            self::FAMILY_SCHEDULE,
            $this->scheduleFingerprint($event),
            fn (Participant $p) => new EventChangedMail($event, $p),
            requirePrevious: true,
        );
    }

    public function announceCancellation(Event $event): int
    {
        return $this->fanOut(
            $event,
            self::TYPE_CANCELLED,
            self::FAMILY_STATUS,
            self::TYPE_CANCELLED,
            fn (Participant $p) => new EventCancelledMail($event, $p),
        );
    }

    /**
     * "Findet doch statt" — nur an die, die vorher die Absage bekommen haben.
     */
    public function announceReopening(Event $event): int
    {
        return $this->fanOut(
            $event,
            self::TYPE_REOPENED,
            self::FAMILY_STATUS,
            self::TYPE_REOPENED,
            fn (Participant $p) => new EventReopenedMail($event, $p),
            requirePrevious: self::TYPE_CANCELLED,
        );
    }

    public function sendManageLink(Event $event, string $email): bool
    {
        // Mehrfach anforderbar (Geraetewechsel) → zeitbasierter Key
        $hash = self::hashAddress($email);

        return $this->deliver(
            $event,
            $email,
            fn () => new ManageLinkMail($event),
            [
                'type' => self::TYPE_MANAGE_LINK,
                'recipient_hash' => $hash,
                'dedupe_key' => self::TYPE_MANAGE_LINK.':'.$event->id.':'.$hash.':'.now()->timestamp,
            ],
        );
    }

    /**
     * @param  bool|string  $requirePrevious  true = irgendeine Vorgaenger-Mail der Familie,
     *                                        string = Vorgaenger-Mail mit genau diesem Fingerprint
     */
    private function fanOut(
        Event $event,
        string $type,
        array $family,
        string $fingerprint,
        callable $factory,
        bool|string $requirePrevious = false,
    ): int {
        $sent = 0;

        foreach ($event->participants()->whereNotNull('email')->get() as $participant) {
            $previous = MailNotification::where('event_id', $event->id)
                ->where('participant_id', $participant->id)
                ->whereIn('type', $family)
                ->whereNotNull('sent_at')
                ->latest('id')
                ->first();

            if ($previous?->fingerprint === $fingerprint) {
                continue;
            }

            if ($requirePrevious === true && ! $previous) {
                continue;
            }

            if (is_string($requirePrevious) && $previous?->fingerprint !== $requirePrevious) {
                continue;
            }

            $delivered = $this->deliver($event, $participant->email, fn () => $factory($participant), [
                'type' => $type,
                'participant_id' => $participant->id,
                'fingerprint' => $fingerprint,
                'dedupe_key' => implode(':', [$type, $event->id, $participant->id, $previous->id ?? 0]),
            ]);

            if ($delivered) {
                $sent++;
            }
        }

        return $sent;
    }

    private function deliver(Event $event, string $email, callable $factory, array $record): bool
    {
        if (MailNotification::where('dedupe_key', $record['dedupe_key'])->exists()) {
            return false;
        }

        try {
            $notification = MailNotification::create(['event_id' => $event->id, ...$record]);
        } catch (UniqueConstraintViolationException) {
            // paralleler Request hat dieselbe Mail gerade angelegt
            return false;
        }

        try {
            Mail::to($email)->send($factory());
            $notification->update(['sent_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            $error = class_basename($e).': '.self::scrub($e->getMessage());

            Log::warning('Mail failed', ['type' => $record['type'], 'event' => $event->id, 'error' => $error]);
            // Key freigeben, damit ein spaeterer Versuch nicht am Protokoll haengt
            $notification->update([
                'error' => mb_substr($error, 0, 1000),
                'dedupe_key' => $record['dedupe_key'].':failed:'.$notification->id,
            ]);

            return false;
        }
    }

    /** SMTP-Fehler nennen oft die Adresse */
    public static function scrub(string $message): string
    {
        return preg_replace('/[^\s<>"\'@]+@[^\s<>"\'@]+/', '[address]', $message);
    }

    private function scheduleFingerprint(Event $event): string
    {
        $option = $event->decidedOption()->first();

        return hash('sha256', json_encode([
            $option?->starts_at_utc?->toIso8601String(),
            (bool) $option?->all_day,
            $option?->day?->toDateString(),
            $event->location,
        ]));
    }
}
