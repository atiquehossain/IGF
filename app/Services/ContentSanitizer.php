<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Sabberworm\CSS\CSSList\CSSList;
use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser as CssParser;
use Sabberworm\CSS\Property\AtRule;
use Sabberworm\CSS\Property\Charset;
use Sabberworm\CSS\Property\CSSNamespace;
use Sabberworm\CSS\Property\Import;
use Sabberworm\CSS\RuleSet\RuleSet;
use Sabberworm\CSS\Value\CSSFunction;
use Sabberworm\CSS\Value\URL;
use Sabberworm\CSS\Value\ValueList;
use Throwable;

class ContentSanitizer
{
    private const BLOCKED_CSS_AT_RULES = [
        'charset', 'document', 'font-face', 'import', 'namespace', '-moz-document',
    ];

    private const BLOCKED_CSS_FUNCTIONS = [
        'cross-fade', 'expression', 'image', 'image-set', 'paint', 'src', 'url',
        '-webkit-cross-fade', '-webkit-image-set',
    ];

    private const BLOCKED_CSS_PROPERTIES = [
        'behavior', 'src', '-moz-binding',
    ];

    private const ALLOWED_TAGS = [
        'a', 'article', 'aside', 'b', 'blockquote', 'br', 'caption', 'code',
        'dd', 'div', 'dl', 'dt', 'em', 'figcaption', 'figure', 'h1', 'h2',
        'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'ol', 'p', 'pre',
        's', 'section', 'small', 'span', 'strong', 'sub', 'sup', 'table',
        'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul',
    ];

    private const GLOBAL_ATTRIBUTES = [
        'aria-describedby', 'aria-hidden', 'aria-label', 'class', 'id', 'role', 'title',
    ];

    private const TAG_ATTRIBUTES = [
        'a' => ['href', 'rel', 'target'],
        'img' => ['alt', 'height', 'loading', 'src', 'width'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
    ];

    public function sanitizeHtml(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $previousErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="igf-sanitizer-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        $xpath = new DOMXPath($document);
        $root = $xpath->query('//*[@id="igf-sanitizer-root"]')->item(0);

        if (!$root instanceof DOMElement) {
            return '';
        }

        $elements = [];
        foreach ($xpath->query('.//*', $root) as $element) {
            if ($element instanceof DOMElement) {
                $elements[] = $element;
            }
        }

        foreach ($elements as $element) {
            if (!$element->parentNode) {
                continue;
            }

            $tag = strtolower($element->tagName);

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->removeElement($element, in_array($tag, ['script', 'style', 'template'], true));
                continue;
            }

            $allowedAttributes = array_merge(
                self::GLOBAL_ATTRIBUTES,
                self::TAG_ATTRIBUTES[$tag] ?? []
            );

            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->name);

                if (str_starts_with($name, 'on') || !in_array($name, $allowedAttributes, true)) {
                    $element->removeAttribute($attribute->name);
                    continue;
                }

                if (in_array($name, ['href', 'src'], true)) {
                    $safeUrl = $this->sanitizeUrl($attribute->value);
                    if ($safeUrl === '') {
                        $element->removeAttribute($attribute->name);
                    } else {
                        $element->setAttribute($attribute->name, $safeUrl);
                    }
                }
            }

            if ($tag === 'a' && strtolower($element->getAttribute('target')) === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $safeHtml = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $safeHtml .= $document->saveHTML($child);
        }

        return trim($safeHtml);
    }

    public function sanitizeCss(?string $css): string
    {
        $css = mb_substr((string) $css, 0, 50000);
        $css = preg_replace('/\/\*.*?\*\//s', '', $css) ?? '';
        $css = str_ireplace(['</style', '<script', '</script'], '', $css);

        if (trim($css) === '') {
            return '';
        }

        try {
            $document = (new CssParser($css))->parse();
            $this->sanitizeCssList($document);

            foreach ($document->getAllRuleSets() as $ruleSet) {
                $this->sanitizeCssRuleSet($ruleSet);
            }

            return trim($document->render(OutputFormat::createCompact()));
        } catch (Throwable) {
            // Invalid CSS is discarded rather than passed through to a browser parser.
            return '';
        }
    }

    public function sanitizeUrl(mixed $url): string
    {
        if (!is_string($url)) {
            return '';
        }

        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $url = preg_replace('/[\x00-\x1F\x7F\s]+/u', '', $url) ?? '';

        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme === '') {
            return str_starts_with($url, '//') ? 'https:' . $url : $url;
        }

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true) ? $url : '';
    }

    public function sanitizeBlockContent(?array $content): array
    {
        return $this->sanitizeBlockValue($content ?? []);
    }

    private function sanitizeBlockValue(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitizeBlockValue($childValue, (string) $childKey);
            }

            return $sanitized;
        }

        if (!is_string($value)) {
            return $value;
        }

        if (in_array($key, ['body', 'html'], true)) {
            return $this->sanitizeHtml($value);
        }

        if ($this->isBlockUrlKey($key)) {
            return $this->sanitizeUrl($value);
        }

        return str_replace("\0", '', $value);
    }

    private function isBlockUrlKey(?string $key): bool
    {
        $key = strtolower((string) $key);

        return $key === 'url'
            || str_ends_with($key, '_url')
            || in_array($key, ['background_image', 'href', 'image', 'poster', 'src'], true);
    }

    private function sanitizeCssList(CSSList $list): void
    {
        foreach ($list->getContents() as $item) {
            if ($item instanceof Import || $item instanceof Charset || $item instanceof CSSNamespace) {
                $list->remove($item);
                continue;
            }

            if ($item instanceof AtRule) {
                $name = $this->normalizeCssIdentifier($item->atRuleName());
                $arguments = method_exists($item, 'atRuleArgs') ? (string) $item->atRuleArgs() : '';

                if (in_array($name, self::BLOCKED_CSS_AT_RULES, true)
                    || $this->cssTextReferencesExternalResource($arguments)) {
                    $list->remove($item);
                    continue;
                }
            }

            if ($item instanceof CSSList) {
                $this->sanitizeCssList($item);
            }
        }
    }

    private function sanitizeCssRuleSet(RuleSet $ruleSet): void
    {
        foreach ($ruleSet->getDeclarations() as $declaration) {
            $property = $this->normalizeCssIdentifier($declaration->getPropertyName());
            $value = $declaration->getValue();

            if (in_array($property, self::BLOCKED_CSS_PROPERTIES, true)
                || $this->cssValueReferencesExternalResource($value)) {
                $ruleSet->removeDeclaration($declaration);
            }
        }
    }

    private function cssValueReferencesExternalResource(mixed $value): bool
    {
        if ($value instanceof URL) {
            return true;
        }

        if ($value instanceof CSSFunction) {
            if (in_array($this->normalizeCssIdentifier($value->getName()), self::BLOCKED_CSS_FUNCTIONS, true)) {
                return true;
            }

            foreach ($value->getArguments() as $argument) {
                if ($this->cssValueReferencesExternalResource($argument)) {
                    return true;
                }
            }

            return false;
        }

        if ($value instanceof ValueList) {
            foreach ($value->getListComponents() as $component) {
                if ($this->cssValueReferencesExternalResource($component)) {
                    return true;
                }
            }
        }

        if (is_string($value)) {
            return $this->cssTextReferencesExternalResource($value);
        }

        return false;
    }

    private function cssTextReferencesExternalResource(string $value): bool
    {
        $value = $this->normalizeCssSecurityText($value);

        return preg_match(
            '/(?:url|src|image|image-set|cross-fade|paint|-webkit-image-set|-webkit-cross-fade)\(/',
            $value
        ) === 1 || preg_match('/(?:javascript|vbscript|data):/', $value) === 1;
    }

    private function normalizeCssIdentifier(string $value): string
    {
        return strtolower(trim($this->decodeCssEscapes($value)));
    }

    private function normalizeCssSecurityText(string $value): string
    {
        $value = strtolower($this->decodeCssEscapes($value));

        return preg_replace('/[\x00-\x20\x7F]+/u', '', $value) ?? '';
    }

    private function decodeCssEscapes(string $value): string
    {
        return preg_replace_callback(
            '/\\\\(?:([0-9a-fA-F]{1,6})(?:\r\n|[\t\n\r\f ])?|([^\r\n\f]))/',
            static function (array $match): string {
                if (($match[1] ?? '') === '') {
                    return $match[2] ?? '';
                }

                $codePoint = hexdec($match[1]);
                if ($codePoint === 0 || $codePoint > 0x10FFFF || ($codePoint >= 0xD800 && $codePoint <= 0xDFFF)) {
                    return "\u{FFFD}";
                }

                return mb_chr($codePoint, 'UTF-8');
            },
            $value
        ) ?? '';
    }

    private function removeElement(DOMElement $element, bool $removeContents): void
    {
        $parent = $element->parentNode;
        if (!$parent instanceof DOMNode) {
            return;
        }

        if ($removeContents) {
            $parent->removeChild($element);
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }
}
