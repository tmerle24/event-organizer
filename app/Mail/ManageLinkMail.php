<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Der Verwaltungslink ist der einzige Weg zurueck in die Organisator-Ansicht,
 * wenn der LocalStorage weg ist. Diese Mail geht deshalb ausschliesslich an
 * die Adresse, die im Manage-Bereich selbst eingetragen wurde.
 */
class ManageLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Event $event) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.manage_link.subject', ['title' => $this->event->title]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.manage-link', with: [
            'event' => $this->event,
            'url' => $this->event->manageUrl(),
            'publicUrl' => $this->event->publicUrl(),
        ]);
    }
}
