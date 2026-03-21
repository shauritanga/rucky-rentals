<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManagerWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $managerName,
        public string $email,
        public string $initialPassword,
        public string $loginUrl,
        public ?string $propertyName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to the Rucky Rentals Management Team',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.manager-welcome',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
