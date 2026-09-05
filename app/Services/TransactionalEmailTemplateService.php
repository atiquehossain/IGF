<?php

namespace App\Services;

use App\Data\RenderedTransactionalEmail;
use App\Models\TransactionalEmailTemplate;
use App\Support\AdminUi;
use App\Support\TransactionalEmailTemplateCatalog;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class TransactionalEmailTemplateService
{
    private const STRUCTURED_MARKER_VERSION = 1;

    private const STRUCTURED_LIMITS = [
        'subject' => 200,
        'heading' => 200,
        'introduction' => 1200,
        'body' => 6000,
        'button_label' => 120,
        'button_url' => 2048,
        'closing' => 1500,
    ];

    public function __construct(
        private readonly TransactionalEmailContentSanitizer $sanitizer,
        private readonly SiteSettingService $siteSettings,
    ) {}

    public function normalizeLocale(?string $locale): string
    {
        return in_array($locale, TransactionalEmailTemplateCatalog::LOCALES, true)
            ? (string) $locale
            : 'en';
    }

    /**
     * @return array{
     *   subject: string,
     *   html_body: string,
     *   text_body: string,
     *   is_custom: bool,
     *   record: ?TransactionalEmailTemplate
     * }
     */
    public function editorContent(string $templateKey, string $locale): array
    {
        $this->assertSupported($templateKey, $locale);
        $record = Schema::hasTable('transactional_email_templates')
            ? TransactionalEmailTemplate::query()
                ->where('template_key', $templateKey)
                ->where('locale', $locale)
                ->first()
            : null;
        $content = $record
            ? $record->only(['subject', 'html_body', 'text_body'])
            : TransactionalEmailTemplateCatalog::defaults($templateKey, $locale);

        return $content + ['is_custom' => $record !== null, 'record' => $record];
    }

    /**
     * @return array{
     *   subject: string,
     *   heading: string,
     *   introduction: string,
     *   body: string,
     *   button_label?: string,
     *   button_url?: string,
     *   closing: string,
     *   is_custom: bool,
     *   is_legacy: bool
     * }
     */
    public function structuredEditorContent(string $templateKey, string $locale): array
    {
        $content = $this->editorContent($templateKey, $locale);
        $structured = $this->extractStructuredMarker($content['html_body']);
        if ($structured !== null
            && !$this->structuredMarkerMatchesStoredContent($templateKey, $locale, $structured, $content)) {
            $structured = null;
        }
        $isLegacy = false;

        if ($structured === null) {
            $structured = $content['is_custom']
                ? $this->legacyStructuredContent($templateKey, $locale, $content)
                : TransactionalEmailTemplateCatalog::structuredDefaults($templateKey, $locale);
            $isLegacy = $content['is_custom'];
        }

        $defaults = TransactionalEmailTemplateCatalog::structuredDefaults($templateKey, $locale);
        $fields = [];
        foreach ($this->structuredFieldNames($templateKey) as $field) {
            $value = $structured[$field] ?? $defaults[$field] ?? '';
            $fields[$field] = is_string($value) ? str_replace("\0", '', $value) : '';
        }

        return $fields + [
            'is_custom' => (bool) $content['is_custom'],
            'is_legacy' => $isLegacy,
        ];
    }

    /** @return array<string, array<int, string>> */
    public function structuredValidationRules(string $templateKey, string $locale): array
    {
        $this->assertSupported($templateKey, $locale);
        $rules = [];
        foreach ($this->structuredFieldNames($templateKey) as $field) {
            $rules[$field] = ['required', 'string', 'max:'.self::STRUCTURED_LIMITS[$field]];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function structuredValidationMessages(string $templateKey, string $locale): array
    {
        $this->assertSupported($templateKey, $locale);
        $messages = [];
        foreach ($this->structuredFieldNames($templateKey) as $field) {
            $label = AdminUi::text("email_templates.fields.{$field}");
            $messages["{$field}.required"] = AdminUi::text(
                'email_templates.validation.structured_required',
                ['field' => $label]
            );
            $messages["{$field}.string"] = AdminUi::text(
                'email_templates.validation.structured_string',
                ['field' => $label]
            );
            $messages["{$field}.max"] = AdminUi::text(
                'email_templates.validation.structured_max',
                ['field' => $label, 'max' => self::STRUCTURED_LIMITS[$field]]
            );
        }

        return $messages;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array{subject: string, html_body: string, text_body: string}
     */
    public function sanitizeStructuredForStorage(
        string $templateKey,
        string $locale,
        array $fields
    ): array {
        $this->assertSupported($templateKey, $locale);
        $definition = TransactionalEmailTemplateCatalog::definition($templateKey);
        $allowed = array_keys($definition['placeholders']);
        $normalized = [];

        foreach ($this->structuredFieldNames($templateKey) as $field) {
            $value = (string) ($fields[$field] ?? '');
            if (mb_strlen($value) > self::STRUCTURED_LIMITS[$field]) {
                throw ValidationException::withMessages([
                    $field => AdminUi::text(
                        'email_templates.validation.structured_max',
                        [
                            'field' => AdminUi::text("email_templates.fields.{$field}"),
                            'max' => self::STRUCTURED_LIMITS[$field],
                        ]
                    ),
                ]);
            }
            if ($field === 'subject' && preg_match('/[\r\n]/', $value) === 1) {
                throw ValidationException::withMessages([
                    'subject' => AdminUi::text('email_templates.validation.single_line_subject'),
                ]);
            }
            $this->validateSyntaxAndPlaceholders($field, $value, $allowed);

            if ($field === 'button_url') {
                $value = $this->sanitizeButtonUrl($value, $definition['url_placeholders']);
            } elseif (in_array($field, ['subject', 'heading', 'button_label'], true)) {
                $value = $this->singleLine($value, self::STRUCTURED_LIMITS[$field]);
            } else {
                $value = mb_substr($this->sanitizer->plain($value), 0, self::STRUCTURED_LIMITS[$field]);
            }

            if ($value === '') {
                throw ValidationException::withMessages([
                    $field => AdminUi::text(
                        'email_templates.validation.structured_required',
                        ['field' => AdminUi::text("email_templates.fields.{$field}")]
                    ),
                ]);
            }

            $this->validateSyntaxAndPlaceholders($field, $value, $allowed);
            $normalized[$field] = $value;
        }

        $compiled = $this->compileStructuredBodies($templateKey, $normalized);
        $safe = $this->sanitizeForStorage(
            $templateKey,
            $locale,
            $normalized['subject'],
            $compiled['html_body'],
            $compiled['text_body']
        );
        $marker = $this->structuredMarker($normalized);
        if (mb_strlen($safe['html_body'].$marker) > 30000) {
            throw ValidationException::withMessages([
                'body' => AdminUi::text('email_templates.validation.generated_too_long'),
            ]);
        }
        $safe['html_body'] .= $marker;

        return $safe;
    }

    /**
     * @return array{subject: string, html_body: string, text_body: string}
     * @throws ValidationException
     */
    public function sanitizeForStorage(
        string $templateKey,
        string $locale,
        string $subject,
        string $htmlBody,
        string $textBody
    ): array {
        $this->assertSupported($templateKey, $locale);
        $definition = TransactionalEmailTemplateCatalog::definition($templateKey);
        $allowed = array_keys($definition['placeholders']);

        $this->validateSyntaxAndPlaceholders('subject', $subject, $allowed);
        $this->validateSyntaxAndPlaceholders('html_body', $htmlBody, $allowed);
        $this->validateSyntaxAndPlaceholders('text_body', $textBody, $allowed);
        if (preg_match('/[\r\n]/', $subject) === 1) {
            throw ValidationException::withMessages([
                'subject' => AdminUi::text('email_templates.validation.single_line_subject'),
            ]);
        }

        $safe = [
            'subject' => $this->sanitizer->subject($subject),
            'html_body' => $this->sanitizer->rich($htmlBody, $definition['url_placeholders']),
            'text_body' => $this->sanitizer->plain($textBody),
        ];

        foreach ($safe as $field => $value) {
            if ($value === '') {
                throw ValidationException::withMessages([
                    $field => AdminUi::text('email_templates.validation.empty_after_sanitization'),
                ]);
            }
            $this->validateSyntaxAndPlaceholders($field, $value, $allowed);
        }

        foreach ($definition['required_in_each_body'] as $required) {
            foreach (['html_body', 'text_body'] as $field) {
                if (!in_array($required, $this->tokens($safe[$field]), true)) {
                    throw ValidationException::withMessages([
                        $field => AdminUi::text(
                            'email_templates.validation.required_placeholder',
                            ['placeholder' => $required]
                        ),
                    ]);
                }
            }
        }

        return $safe;
    }

    /** @param array<string, mixed> $variables */
    public function render(string $templateKey, ?string $locale, array $variables): RenderedTransactionalEmail
    {
        $locale = $this->normalizeLocale($locale);
        $this->assertSupported($templateKey, $locale);
        $definition = TransactionalEmailTemplateCatalog::definition($templateKey);
        $content = $this->safeContentForDelivery($templateKey, $locale);
        $variables = array_intersect_key(
            array_merge($this->commonVariables($locale), $variables),
            $definition['placeholders']
        );

        $subject = $this->renderFragment($content['subject'], $variables, false, true);
        $html = $this->renderFragment($this->stripStructuredMarker($content['html_body']), $variables, true);
        $text = $this->renderFragment($content['text_body'], $variables, false);

        return new RenderedTransactionalEmail(
            $templateKey,
            $locale,
            $this->sanitizer->subject($subject),
            $this->sanitizer->rich($html),
            $this->sanitizer->plain($text),
        );
    }

    /** @return array{subject: string, html_body: string, text_body: string} */
    private function safeContentForDelivery(string $templateKey, string $locale): array
    {
        $candidate = $this->editorContent($templateKey, $locale);

        try {
            return $this->sanitizeForStorage(
                $templateKey,
                $locale,
                $candidate['subject'],
                $candidate['html_body'],
                $candidate['text_body']
            );
        } catch (Throwable $exception) {
            if (!$candidate['is_custom']) {
                throw $exception;
            }

            Log::warning('Invalid transactional email override ignored.', [
                'template_key' => $templateKey,
                'locale' => $locale,
                'exception_class' => $exception::class,
            ]);
            $defaults = TransactionalEmailTemplateCatalog::defaults($templateKey, $locale);

            return $this->sanitizeForStorage(
                $templateKey,
                $locale,
                $defaults['subject'],
                $defaults['html_body'],
                $defaults['text_body']
            );
        }
    }

    /** @return array<string, string> */
    private function commonVariables(string $locale): array
    {
        $siteName = trim((string) data_get(
            $this->siteSettings->values($locale, true),
            'branding.site_name',
            ''
        ));

        return [
            'site_name' => $siteName !== ''
                ? $siteName
                : (string) config('app.name', 'Ignite Global Foundation'),
            'site_url' => rtrim((string) config('app.url'), '/'),
            'contact_email' => trim((string) config('transactional-mail.contact_address')),
        ];
    }

    /** @param array<string, mixed> $variables */
    private function renderFragment(
        string $template,
        array $variables,
        bool $html,
        bool $singleLine = false
    ): string {
        return (string) preg_replace_callback(
            '/{{\s*([a-z][a-z0-9_]*)\s*}}/u',
            function (array $match) use ($variables, $html, $singleLine): string {
                $key = $match[1];
                if (!array_key_exists($key, $variables)) {
                    throw new RuntimeException("Missing transactional email value [{$key}].");
                }

                $value = $this->sanitizer->plain((string) ($variables[$key] ?? ''));
                if ($singleLine) {
                    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
                }

                return $html ? e($value) : $value;
            },
            $template
        );
    }

    /** @param list<string> $allowed */
    private function validateSyntaxAndPlaceholders(string $field, string $content, array $allowed): void
    {
        if (preg_match('/{!!|@php\b|<\?(?:php|=)?/i', $content) === 1) {
            throw ValidationException::withMessages([
                $field => AdminUi::text('email_templates.validation.executable_syntax'),
            ]);
        }

        $withoutTokens = preg_replace('/{{\s*[a-z][a-z0-9_]*\s*}}/u', '', $content) ?? $content;
        if (str_contains($withoutTokens, '{{') || str_contains($withoutTokens, '}}')) {
            throw ValidationException::withMessages([
                $field => AdminUi::text('email_templates.validation.malformed_placeholder'),
            ]);
        }

        $unknown = array_values(array_diff($this->tokens($content), $allowed));
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                $field => AdminUi::text(
                    'email_templates.validation.unsupported_placeholder',
                    ['placeholder' => $unknown[0]]
                ),
            ]);
        }
    }

    /** @return list<string> */
    private function tokens(string $content): array
    {
        preg_match_all('/{{\s*([a-z][a-z0-9_]*)\s*}}/u', $content, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /** @return list<string> */
    private function structuredFieldNames(string $templateKey): array
    {
        $fields = ['subject', 'heading', 'introduction', 'body'];
        if (TransactionalEmailTemplateCatalog::usesButton($templateKey)) {
            $fields[] = 'button_label';
            $fields[] = 'button_url';
        }
        $fields[] = 'closing';

        return $fields;
    }

    /**
     * @param array<string, string> $fields
     * @return array{html_body: string, text_body: string}
     */
    private function compileStructuredBodies(string $templateKey, array $fields): array
    {
        $html = '<h1>'.e($fields['heading']).'</h1>'
            .$this->plainParagraphs($fields['introduction'])
            .$this->plainParagraphs($fields['body']);
        $textParts = [
            $fields['heading'],
            $fields['introduction'],
            $fields['body'],
        ];

        if (TransactionalEmailTemplateCatalog::usesButton($templateKey)) {
            $html .= '<p><a href="'.e($fields['button_url']).'">'.e($fields['button_label']).'</a></p>';
            $textParts[] = $fields['button_label'].': '.$fields['button_url'];
        }

        $html .= $this->plainParagraphs($fields['closing']);
        $textParts[] = $fields['closing'];

        return [
            'html_body' => $html,
            'text_body' => implode("\n\n", $textParts),
        ];
    }

    private function plainParagraphs(string $value): string
    {
        $paragraphs = preg_split('/\n{2,}/u', trim($value)) ?: [];
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            $html .= '<p>'.nl2br(e($paragraph), false).'</p>';
        }

        return $html;
    }

    /** @param list<string> $urlPlaceholders */
    private function sanitizeButtonUrl(string $value, array $urlPlaceholders): string
    {
        $value = trim($value);
        if (preg_match('/\A{{\s*([a-z][a-z0-9_]*)\s*}}\z/u', $value, $match) === 1) {
            if (in_array($match[1], $urlPlaceholders, true)) {
                return '{{'.$match[1].'}}';
            }

            throw ValidationException::withMessages([
                'button_url' => AdminUi::text('email_templates.validation.button_url_placeholder'),
            ]);
        }
        if (str_contains($value, '{{') || str_contains($value, '}}')) {
            throw ValidationException::withMessages([
                'button_url' => AdminUi::text('email_templates.validation.button_url_placeholder'),
            ]);
        }

        $safe = $this->sanitizer->url($value);
        if ($safe === '') {
            throw ValidationException::withMessages([
                'button_url' => AdminUi::text('email_templates.validation.button_url'),
            ]);
        }

        return mb_substr($safe, 0, self::STRUCTURED_LIMITS['button_url']);
    }

    private function singleLine(string $value, int $limit): string
    {
        $value = $this->sanitizer->plain($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return mb_substr(trim($value), 0, $limit);
    }

    /** @param array<string, string> $fields */
    private function structuredMarker(array $fields): string
    {
        $json = json_encode(
            ['version' => self::STRUCTURED_MARKER_VERSION, 'fields' => $fields],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($json)) {
            throw new RuntimeException('The structured email copy could not be encoded.');
        }
        $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');

        return "\n<!--igf-email-structured:{$encoded}-->";
    }

    /** @return array<string, string>|null */
    private function extractStructuredMarker(string $html): ?array
    {
        if (preg_match('/<!--igf-email-structured:([A-Za-z0-9_-]+)-->/u', $html, $match) !== 1) {
            return null;
        }
        $encoded = strtr($match[1], '-_', '+/');
        $padding = strlen($encoded) % 4;
        if ($padding !== 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }
        $json = base64_decode($encoded, true);
        $decoded = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($decoded)
            || ($decoded['version'] ?? null) !== self::STRUCTURED_MARKER_VERSION
            || !is_array($decoded['fields'] ?? null)) {
            return null;
        }

        $fields = [];
        foreach ($decoded['fields'] as $field => $value) {
            if (is_string($field) && is_string($value) && array_key_exists($field, self::STRUCTURED_LIMITS)) {
                $fields[$field] = $value;
            }
        }

        return $fields;
    }

    private function stripStructuredMarker(string $html): string
    {
        return trim((string) preg_replace(
            '/\s*<!--igf-email-structured:[A-Za-z0-9_-]+-->\s*/u',
            '',
            $html
        ));
    }

    /**
     * A marker is editor metadata, not proof by itself. Recompile its guided
     * fields and compare them with the content that would actually be sent so
     * a forged comment cannot hide different legacy HTML from an administrator.
     *
     * @param array<string, string> $structured
     * @param array<string, mixed> $content
     */
    private function structuredMarkerMatchesStoredContent(
        string $templateKey,
        string $locale,
        array $structured,
        array $content
    ): bool {
        $fieldNames = $this->structuredFieldNames($templateKey);
        if (array_diff($fieldNames, array_keys($structured)) !== []) {
            return false;
        }

        try {
            $compiled = $this->sanitizeStructuredForStorage(
                $templateKey,
                $locale,
                array_intersect_key($structured, array_flip($fieldNames))
            );
            $actual = $this->sanitizeForStorage(
                $templateKey,
                $locale,
                (string) ($content['subject'] ?? ''),
                (string) ($content['html_body'] ?? ''),
                (string) ($content['text_body'] ?? '')
            );
        } catch (Throwable) {
            return false;
        }

        return hash_equals($compiled['subject'], $actual['subject'])
            && hash_equals($this->stripStructuredMarker($compiled['html_body']), $actual['html_body'])
            && hash_equals($compiled['text_body'], $actual['text_body']);
    }

    /**
     * Existing rows keep rendering through their original sanitized HTML/text.
     * This best-effort mapping only prepares those rows for the guided editor;
     * conversion happens after the administrator explicitly saves.
     *
     * @param array<string, mixed> $content
     * @return array<string, string>
     */
    private function legacyStructuredContent(
        string $templateKey,
        string $locale,
        array $content
    ): array {
        $defaults = TransactionalEmailTemplateCatalog::structuredDefaults($templateKey, $locale);
        $safeHtml = $this->sanitizer->rich((string) $content['html_body'],
            TransactionalEmailTemplateCatalog::definition($templateKey)['url_placeholders'] ?? []);
        $previousErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="igf-legacy-email">'.$safeHtml.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $xpath = new DOMXPath($document);
        $root = $xpath->query('//*[@id="igf-legacy-email"]')->item(0);
        if (!$root instanceof DOMElement) {
            return $defaults;
        }

        $headingNode = $xpath->query('.//h1|.//h2|.//h3', $root)->item(0);
        $anchor = $xpath->query('.//a', $root)->item(0);
        $paragraphs = [];
        foreach ($xpath->query('.//p[not(.//a)]', $root) as $paragraph) {
            $text = trim($this->nodeText($paragraph));
            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        $introduction = array_shift($paragraphs) ?: ($defaults['introduction'] ?? '');
        $closing = count($paragraphs) >= 2
            ? (string) array_pop($paragraphs)
            : ($defaults['closing'] ?? '');
        $body = trim(implode("\n\n", $paragraphs));
        if ($body === '') {
            $body = $defaults['body'] ?? $this->sanitizer->plain((string) $content['text_body']);
        }

        $mapped = [
            'subject' => (string) $content['subject'],
            'heading' => $headingNode instanceof DOMNode
                ? trim($this->nodeText($headingNode))
                : ($defaults['heading'] ?? ''),
            'introduction' => $introduction,
            'body' => $body,
            'closing' => $closing,
        ];
        if (TransactionalEmailTemplateCatalog::usesButton($templateKey)) {
            $mapped['button_label'] = $anchor instanceof DOMElement
                ? trim($this->nodeText($anchor))
                : ($defaults['button_label'] ?? '');
            $mapped['button_url'] = $anchor instanceof DOMElement
                ? trim($anchor->getAttribute('href'))
                : ($defaults['button_url'] ?? '');
        }

        return $mapped;
    }

    private function nodeText(DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'br') {
                $text .= "\n";
            } elseif ($child->hasChildNodes()) {
                $text .= $this->nodeText($child);
            } else {
                $text .= $child->nodeValue ?? '';
            }
        }

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function assertSupported(string $templateKey, string $locale): void
    {
        if (!TransactionalEmailTemplateCatalog::supports($templateKey, $locale)) {
            throw new InvalidArgumentException('Unsupported transactional email template identity.');
        }
    }
}
