<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Event $event, public ?string $name = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.invite.subject', ['title' => $this->event->title]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.invite', with: [
            'event' => $this->event,
            'name' => $this->name,
            'url' => $this->event->publicUrl(),
        ]);
    }
}
