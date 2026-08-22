<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TechnicalSeoAuditNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject($this->title)
            ->error()
            ->greeting('Technical SEO monitoring alert')
            ->line($this->message)
            ->line('Open the protected administration area to review the affected paths and recommended actions.')
            ->action('Open Technical SEO Center', route('seo.technical.index'));
    }
}
