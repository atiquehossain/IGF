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

    public function __construct(
        private readonly string $confirmationUrl,
        private readonly string $contentLocale = 'en',
        private readonly array $copy = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: trim((string) ($this->copy['newsletter_confirmation_subject'] ?? ''))
            ?: 'Confirm your Ignite email subscription');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter-confirmation',
            with: [
                'confirmationUrl' => $this->confirmationUrl,
                'locale' => $this->contentLocale,
                'copy' => $this->emailCopy(),
            ],
        );
    }

    public function confirmationUrl(): string
    {
        return $this->confirmationUrl;
    }

    /** @return array<string, string> */
    public function emailCopy(): array
    {
        $ttlMinutes = max(1, (int) config('privacy.newsletter.confirmation_ttl_minutes', 1440));
        $hours = (string) max(1, (int) ceil($ttlMinutes / 60));
        $fallback = [
            'title' => 'Confirm your email subscription',
            'body' => 'Someone asked to receive Ignite Global Foundation updates at this address. Confirm only if that was you.',
            'button' => 'Confirm subscription',
            'expiry' => 'This link expires in {hours} hours. If you did not request these updates, you can ignore this email and no subscription will be activated.',
        ];
        $keys = [
            'title' => 'newsletter_confirmation_title',
            'body' => 'newsletter_confirmation_body',
            'button' => 'newsletter_confirmation_button',
            'expiry' => 'newsletter_confirmation_expiry',
        ];

        foreach ($keys as $target => $source) {
            $value = trim((string) ($this->copy[$source] ?? ''));
            if ($value !== '') {
                $fallback[$target] = $value;
            }
        }
        $fallback['expiry'] = str_replace('{hours}', $hours, $fallback['expiry']);

        return $fallback;
    }

    public function attachments(): array
    {
        return [];
    }
}
