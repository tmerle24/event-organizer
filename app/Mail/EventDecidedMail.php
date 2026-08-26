<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventDecidedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Event $event, public Participant $participant) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.decided.subject', ['title' => $this->event->title]));
    }

    public function content(): Content
    {
        $option = $this->event->decidedOption;

        return new Content(markdown: 'emails.decided', with: [
            'event' => $this->event,
            'participant' => $this->participant,
            'url' => $this->event->publicUrl(),
            'when' => $option
                ? $option->starts_at_utc->setTimezone($this->event->timezone)->format('d.m.Y H:i')
                : null,
            'allDay' => (bool) $option?->all_day,
        ]);
    }
}
