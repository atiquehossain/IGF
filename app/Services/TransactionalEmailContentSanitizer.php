<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class TransactionalEmailContentSanitizer
{
    private const ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'em', 'h1', 'h2', 'h3', 'hr', 'i',
        'li', 'ol', 'p', 'small', 'strong', 'u', 'ul',
    ];

    private const DROP_WITH_CONTENT = [
        'embed', 'iframe', 'math', 'object', 'script', 'style', 'svg', 'template',
    ];

    public function __construct(private readonly ContentSanitizer $contentSanitizer)
    {
    }

    public function subject(?string $subject): string
    {
        $subject = html_entity_decode(strip_tags((string) $subject), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $subject = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $subject) ?? '';
        $subject = preg_replace('/\s+/u', ' ', $subject) ?? '';

        return mb_substr(trim($subject), 0, 200);
    }

    public function plain(?string $body): string
    {
        $body = html_entity_decode(strip_tags((string) $body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $body);
        $body = preg_replace('/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body) ?? '';
        $body = preg_replace('/[ \t]+\n/u', "\n", $body) ?? '';
        $body = preg_replace('/\n{4,}/u', "\n\n\n", $body) ?? '';

        return mb_substr(trim($body), 0, 20000);
    }

    public function url(?string $url): string
    {
        $url = $this->contentSanitizer->sanitizeUrl((string) $url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || trim((string) ($parts['host'] ?? '')) === ''
            || array_key_exists('user', $parts)
            || array_key_exists('pass', $parts)) {
            return '';
        }

        return $url;
    }

    /** @param list<string> $urlPlaceholders */
    public function rich(?string $body, array $urlPlaceholders = []): string
    {
        $body = $this->contentSanitizer->sanitizeHtml(mb_substr((string) $body, 0, 30000));
        if ($body === '') {
            return '';
        }

        $previousErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="igf-email-root">'.$body.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $root = (new DOMXPath($document))->query('//*[@id="igf-email-root"]')->item(0);
        if (!$root instanceof DOMElement) {
            return '';
        }

        // HTML comments are not inert in every mail client. In particular,
        // Outlook interprets MSO conditional comments and could expose markup
        // that bypasses the element/attribute allowlist below. Comments are
        // never part of operator-authored email copy; the application's own
        // structured-editor marker is appended only after this sanitizer runs.
        foreach (iterator_to_array((new DOMXPath($document))->query('.//comment()', $root)) as $comment) {
            $comment->parentNode?->removeChild($comment);
        }

        $elements = [];
        foreach ((new DOMXPath($document))->query('.//*', $root) as $element) {
            if ($element instanceof DOMElement) {
                $elements[] = $element;
            }
        }

        foreach (array_reverse($elements) as $element) {
            if (!$element->parentNode) {
                continue;
            }

            $tag = strtolower($element->tagName);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->removeElement($element, in_array($tag, self::DROP_WITH_CONTENT, true));
                continue;
            }

            foreach (iterator_to_array($element->attributes) as $attribute) {
                if ($tag !== 'a' || !in_array(strtolower($attribute->name), ['href', 'target'], true)) {
                    $element->removeAttribute($attribute->name);
                }
            }

            if ($tag !== 'a') {
                continue;
            }

            $href = trim($element->getAttribute('href'));
            $placeholderHref = rawurldecode($href);
            if (preg_match('/\A{{\s*([a-z][a-z0-9_]*)\s*}}\z/', $placeholderHref, $match) === 1) {
                if (in_array($match[1], $urlPlaceholders, true)) {
                    $element->setAttribute('href', '{{'.$match[1].'}}');
                } else {
                    $element->removeAttribute('href');
                }
            } elseif (str_contains($href, '{{') || str_contains($href, '}}')) {
                $element->removeAttribute('href');
            } else {
                // Email links must remain complete web destinations. Relative,
                // mailto/tel and user-info URLs either break outside the site
                // or can disguise the actual destination shown by a mail client.
                $safeUrl = $this->url($href);
                if ($safeUrl === '') {
                    $element->removeAttribute('href');
                } else {
                    $element->setAttribute('href', $safeUrl);
                }
            }

            if (strtolower($element->getAttribute('target')) === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer');
            } else {
                $element->removeAttribute('target');
                $element->removeAttribute('rel');
            }
        }

        $safe = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $safe .= $document->saveHTML($child);
        }
        foreach ($urlPlaceholders as $placeholder) {
            $safe = str_ireplace(
                rawurlencode('{{'.$placeholder.'}}'),
                '{{'.$placeholder.'}}',
                $safe
            );
        }

        return trim($safe);
    }

    private function removeElement(DOMElement $element, bool $dropChildren): void
    {
        $parent = $element->parentNode;
        if (!$parent instanceof DOMNode) {
            return;
        }

        if (!$dropChildren) {
            while ($element->firstChild) {
                $parent->insertBefore($element->firstChild, $element);
            }
        }

        $parent->removeChild($element);
    }
}
