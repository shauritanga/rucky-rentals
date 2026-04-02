<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManagerWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Retry up to 3 times before moving to failed_jobs */
    public int $tries = 3;

    /** Give up if the SMTP handshake takes longer than 30 seconds */
    public int $timeout = 30;

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
            subject: 'Welcome to the Ruky Rentals Management Team',
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
