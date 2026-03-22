<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $memberName,
        public string $email,
        public string $initialPassword,
        public string $loginUrl,
        public string $roleLabel,
        public ?string $propertyName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Rucky Rentals Team Account Is Ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invite',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
