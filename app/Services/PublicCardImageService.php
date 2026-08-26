<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;

final class PublicCardImageService
{
    public function __construct(private ContentSanitizer $sanitizer)
    {
    }

    /** @return array{url:string,alt:?string}|null */
    public function firstManagedImage(?string $html): ?array
    {
        $html = $this->sanitizer->sanitizeHtml($html);
        if ($html === '') {
            return null;
        }

        $previousErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="igf-card-image-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (!$loaded) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $root = $xpath->query('//*[@id="igf-card-image-root"]')->item(0);
        if (!$root instanceof DOMElement) {
            return null;
        }

        foreach ($xpath->query('.//img', $root) as $image) {
            if (!$image instanceof DOMElement) {
                continue;
            }

            $url = $this->managedPublicUrl($image->getAttribute('src'));
            if ($url === null) {
                continue;
            }

            return [
                'url' => $url,
                'alt' => $image->hasAttribute('alt') ? $this->plainAlt($image->getAttribute('alt')) : null,
            ];
        }

        return null;
    }

    private function managedPublicUrl(string $value): ?string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' || preg_match('/[\x00-\x20\x7F]/u', $value) === 1) {
            return null;
        }

        $parts = parse_url($value);
        if ($parts === false
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        if (str_starts_with($value, '/') && !str_starts_with($value, '//')) {
            $path = $parts['path'] ?? '';
        } else {
            if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                || !$this->isConfiguredHost((string) ($parts['host'] ?? ''), $parts['port'] ?? null)) {
                return null;
            }
            $path = (string) ($parts['path'] ?? '');
        }

        if (preg_match(
            '#\A/storage/(?:media|photos)/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpe?g|png|gif|webp)\z#iD',
            $path
        ) !== 1) {
            return null;
        }

        return $path;
    }

    private function isConfiguredHost(string $host, mixed $port): bool
    {
        $configured = parse_url((string) config('app.url'));
        if (!is_array($configured)
            || $host === ''
            || !hash_equals(strtolower((string) ($configured['host'] ?? '')), strtolower($host))) {
            return false;
        }

        $actualPort = $port === null ? 443 : (int) $port;
        $configuredPort = isset($configured['port'])
            ? (int) $configured['port']
            : (strtolower((string) ($configured['scheme'] ?? 'https')) === 'http' ? 80 : 443);

        return $actualPort === $configuredPort;
    }

    private function plainAlt(string $value): string
    {
        $value = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return mb_substr(trim($value), 0, 255);
    }
}
