<?php

namespace App\Mail;

use App\Services\TransactionalEmailTemplateService;
use App\Support\TransactionalEmailTemplateCatalog;

class ConfirmNewsletterSubscription extends TransactionalEmail
{
    public function __construct(
        private readonly string $confirmationUrl,
        ?string $messageLocale = null,
    )
    {
        $ttlMinutes = max(1, (int) config('privacy.newsletter.confirmation_ttl_minutes', 1440));
        parent::__construct(app(TransactionalEmailTemplateService::class)->render(
            TransactionalEmailTemplateCatalog::NEWSLETTER_CONFIRMATION,
            $messageLocale ?: app()->getLocale(),
            [
                'confirmation_url' => $confirmationUrl,
                'expiry_hours' => (string) max(1, (int) ceil($ttlMinutes / 60)),
            ]
        ));
    }

    public function confirmationUrl(): string
    {
        return $this->confirmationUrl;
    }

}
