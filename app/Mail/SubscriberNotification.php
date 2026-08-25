<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriberNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $notificationSubject,
        private readonly string $notificationBody,
        private readonly ?string $signatureImageUrl = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->notificationSubject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'admin.emails.subscriber_notification',
            with: [
                'body' => $this->notificationBody,
                'signatureImageUrl' => $this->signatureImageUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
