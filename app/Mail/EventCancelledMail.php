<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EventCancelledMail extends ParticipantMail
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.cancelled.subject', ['title' => $this->event->title]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.cancelled', with: $this->baseData());
    }
}
