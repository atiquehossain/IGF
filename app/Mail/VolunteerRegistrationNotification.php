<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VolunteerRegistrationNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $registrationReference,
        private readonly string $reviewUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New volunteer registration · {$this->registrationReference}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.volunteer-registration-notification',
            with: [
                'registrationReference' => $this->registrationReference,
                'reviewUrl' => $this->reviewUrl,
            ],
        );
    }

    public function reference(): string
    {
        return $this->registrationReference;
    }

    public function reviewUrl(): string
    {
        return $this->reviewUrl;
    }

    public function attachments(): array
    {
        return [];
    }
}
