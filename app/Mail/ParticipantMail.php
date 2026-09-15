<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Mails an Teilnehmer mit hinterlegter Adresse — jede mit Abmeldelink
 * (Footer + List-Unsubscribe fuer One-Click in Gmail & Co.).
 */
abstract class ParticipantMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Event $event, public Participant $participant) {}

    public function unsubscribeUrl(): string
    {
        // relativ signiert: unabhaengig von Host/Schema hinter dem Proxy
        return url(URL::signedRoute('public.unsubscribe', [
            'event' => $this->event->public_token,
            'participant' => $this->participant->id,
        ], absolute: false));
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl().'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    protected function baseData(): array
    {
        $option = $this->event->decidedOption;

        return [
            'event' => $this->event,
            'participant' => $this->participant,
            'url' => $this->event->publicUrl(),
            'unsubscribeUrl' => $this->unsubscribeUrl(),
            'when' => $option
                ? ($option->all_day
                    ? $option->day?->format('d.m.Y')
                    : $option->starts_at_utc->setTimezone($this->event->timezone)->format('d.m.Y H:i'))
                : null,
        ];
    }
}
