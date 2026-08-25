<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConfirmNewsletterSubscription extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(private readonly string $confirmationUrl)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your Ignite email subscription');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter-confirmation',
            with: ['confirmationUrl' => $this->confirmationUrl],
        );
    }

    public function confirmationUrl(): string
    {
        return $this->confirmationUrl;
    }

    public function attachments(): array
    {
        return [];
    }
}
