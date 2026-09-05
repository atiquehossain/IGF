<?php

namespace App\Mail;

use App\Data\RenderedTransactionalEmail;
use App\Services\TransactionalEmailDesignService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionalEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(private readonly RenderedTransactionalEmail $messageTemplate)
    {
    }

    public function envelope(): Envelope
    {
        // From, reply-to, recipients and all transport headers deliberately
        // remain outside the database-backed template.
        return new Envelope(subject: $this->messageTemplate->subject);
    }

    public function content(): Content
    {
        $emailDesign = app(TransactionalEmailDesignService::class)
            ->forLocale($this->messageTemplate->locale);

        return new Content(
            view: 'emails.transactional',
            text: 'emails.transactional-text',
            with: [
                'htmlBody' => $this->messageTemplate->htmlBody,
                'textBody' => $this->messageTemplate->textBody,
                'messageLocale' => $this->messageTemplate->locale,
                'messageSubject' => $this->messageTemplate->subject,
                'emailDesign' => $emailDesign,
            ],
        );
    }

    public function renderedTemplate(): RenderedTransactionalEmail
    {
        return $this->messageTemplate;
    }

    public function attachments(): array
    {
        return [];
    }
}
