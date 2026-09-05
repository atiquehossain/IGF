<?php

namespace App\Data;

final readonly class RenderedTransactionalEmail
{
    public function __construct(
        public string $templateKey,
        public string $locale,
        public string $subject,
        public string $htmlBody,
        public string $textBody,
    ) {
    }
}
