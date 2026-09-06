<?php

namespace App\Support;

final class VolunteerConsentEvidence
{
    /**
     * Capture what the visitor accepted without trusting hidden form data.
     *
     * @return array{locale: string, snapshot: string, hash: string}
     */
    public static function fromSettings(array $settings, string $locale): array
    {
        $consent = self::normalize($settings['consent_label'] ?? '');
        $privacyLabel = self::normalize($settings['privacy_link_label'] ?? '');
        $privacyUrl = trim((string) ($settings['privacy_link_url'] ?? ''));
        $snapshot = $consent;

        if ($privacyLabel !== '' && $privacyUrl !== '') {
            $snapshot .= " ({$privacyLabel}: {$privacyUrl})";
        }

        return [
            'locale' => mb_substr(strtolower(trim($locale)), 0, 12),
            'snapshot' => $snapshot,
            'hash' => hash('sha256', $snapshot),
        ];
    }

    private static function normalize(mixed $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        return is_string($normalized) ? $normalized : trim((string) $value);
    }
}
