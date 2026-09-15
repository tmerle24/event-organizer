<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EventChangedMail extends ParticipantMail
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.changed.subject', ['title' => $this->event->title]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.changed', with: $this->baseData());
    }
}
