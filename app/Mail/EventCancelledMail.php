<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Event $event, public Participant $participant) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.cancelled.subject', ['title' => $this->event->title]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.cancelled', with: [
            'event' => $this->event,
            'participant' => $this->participant,
            'url' => $this->event->publicUrl(),
        ]);
    }
}
