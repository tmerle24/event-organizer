<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EventDecidedMail extends ParticipantMail
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.decided.subject', ['title' => $this->event->title]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.decided', with: $this->baseData());
    }
}
