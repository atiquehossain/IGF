<?php

namespace App\Services;

use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SeoContentAnalysisService
{
    private const TEXT_KEYS = [
        'answer',
        'body',
        'caption',
        'description',
        'eyebrow',
        'heading',
        'introduction',
        'label',
        'name',
        'question',
        'quote',
        'sub_title',
        'subtitle',
        'title',
    ];

    private const PARAGRAPH_KEYS = [
        'answer',
        'body',
        'caption',
        'description',
        'introduction',
        'question',
        'quote',
        'sub_title',
        'subtitle',
    ];

    /**
     * Analyze the saved visitor-facing content for a supported SEO owner.
     *
     * The rules deliberately report observable structure and writing signals.
     * They do not calculate keyword density or claim to predict rankings.
     *
     * @return array<string, mixed>
     */
    public function analyze(
        ?Model $model,
        string $type,
        string $locale,
        string $focusPhrase = '',
        string $defaultUrl = ''
    ): array {
        if (!$model) {
            return $this->unavailable($locale);
        }

        $title = trim((string) ($model->getAttribute('name') ?: $model->getAttribute('title')));
        $document = [
            'h1' => $title !== '' ? [$title] : [],
            'h2' => [],
            'html' => [],
            'text' => [],
            'paragraphs' => [],
            'blocks' => [],
        ];

        foreach ($this->modelContentValues($model, $type) as $value) {
            $this->appendValue($document, $value, 'description');
        }

        if ($model instanceof Page) {
            $model->loadMissing('visibleBlocks.reusableBlock');
            /** @var Collection<int, mixed> $blocks */
            $blocks = collect($model->getRelation('visibleBlocks'));
            $heroBlocks = $blocks->filter(fn ($block): bool => (string) $block->type === 'hero');
            if ($heroBlocks->isNotEmpty()) {
                // A hero block replaces the standard page banner, so its
                // heading—not the model title—is the document H1.
                $document['h1'] = [];
            }

            foreach ($blocks as $block) {
                $content = $block->resolvedContent();
                $blockType = (string) $block->type;
                $heading = $this->blockHeading($content);
                if ($heading !== '') {
                    $document[$blockType === 'hero' ? 'h1' : 'h2'][] = $heading;
                }
                $document['blocks'][] = $content;
                $this->appendStructuredContent($document, $content, true);
            }
        }

        return $this->analyzeDocument($document, $locale, $focusPhrase, $defaultUrl, $type);
    }

    /**
     * Analyze an already-normalized document. This public, side-effect-free
     * entry point keeps the EN/BN rules independently testable.
     *
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    public function analyzeDocument(
        array $document,
        string $locale,
        string $focusPhrase = '',
        string $defaultUrl = '',
        string $type = 'page'
    ): array {
        $locale = strtolower(trim($locale)) === 'bn' ? 'bn' : 'en';
        $h1 = $this->cleanStrings((array) ($document['h1'] ?? []));
        $h2 = $this->cleanStrings((array) ($document['h2'] ?? []));
        $text = $this->cleanStrings((array) ($document['text'] ?? []));
        $paragraphs = $this->cleanStrings((array) ($document['paragraphs'] ?? []));
        $images = [];
        $links = [];

        foreach ((array) ($document['html'] ?? []) as $html) {
            if (!is_scalar($html)) {
                continue;
            }
            $this->appendHtmlSignals((string) $html, $h1, $h2, $text, $paragraphs, $images, $links);
        }

        foreach ((array) ($document['blocks'] ?? []) as $block) {
            if (is_array($block)) {
                $this->appendArrayMediaAndLinks($block, $images, $links);
            }
        }

        $h1 = $this->cleanStrings($h1);
        $h2 = $this->cleanStrings($h2);
        $text = $this->cleanStrings($text);
        $paragraphs = $this->cleanStrings($paragraphs);
        // Text fragments already include the plain text extracted from HTML.
        // Paragraphs are kept separately for paragraph-length checks; merging
        // both collections here would count the same visitor copy twice.
        $bodyText = $this->plainText(implode("\n", $text !== [] ? $text : $paragraphs));
        $headingText = $this->plainText(implode("\n", array_merge($h1, $h2)));
        $words = $this->words($bodyText);
        $sentences = $this->sentences($bodyText);
        $sentenceLengths = array_map(fn (string $sentence): int => count($this->words($sentence)), $sentences);
        $paragraphLengths = array_map(fn (string $paragraph): int => count($this->words($paragraph)), $paragraphs);

        $sentenceTarget = $locale === 'bn' ? 22 : 25;
        $longSentenceLimit = $locale === 'bn' ? 30 : 35;
        $longParagraphLimit = $locale === 'bn' ? 90 : 110;
        $longSentenceCount = count(array_filter($sentenceLengths, fn (int $length): bool => $length > $longSentenceLimit));
        $longParagraphCount = count(array_filter($paragraphLengths, fn (int $length): bool => $length > $longParagraphLimit));
        $averageSentenceLength = count($sentenceLengths) > 0
            ? round(array_sum($sentenceLengths) / count($sentenceLengths), 1)
            : 0.0;
        $needsSentenceReview = count($sentences) >= 3
            && ($averageSentenceLength > $sentenceTarget || $longSentenceCount / count($sentences) >= 0.3);

        $internalLinks = 0;
        $externalLinks = 0;
        foreach ($links as $href) {
            $classification = $this->classifyLink((string) $href, $defaultUrl);
            $internalLinks += $classification === 'internal' ? 1 : 0;
            $externalLinks += $classification === 'external' ? 1 : 0;
        }

        $imageCount = count($images);
        $imagesWithAlt = count(array_filter($images, fn (array $image): bool => trim((string) ($image['alt'] ?? '')) !== ''));
        $focusPhrase = $this->normalizeForMatch($focusPhrase);
        $focusInBody = $focusPhrase === '' || str_contains($this->normalizeForMatch($bodyText), $focusPhrase);
        $focusInHeadings = $focusPhrase === '' || str_contains($this->normalizeForMatch($headingText), $focusPhrase);
        $issues = [];

        if (count($h1) === 0) {
            $issues[] = $this->issue('content_missing_h1', 'Add one clear main heading (H1) to the saved page content.', 'danger');
        } elseif (count($h1) > 1) {
            $issues[] = $this->issue('content_multiple_h1', 'Keep one main heading (H1); this page currently has ' . count($h1) . '.', 'warning');
        }

        if (count($words) >= 150 && count($h2) === 0) {
            $issues[] = $this->issue('content_missing_h2', 'Break longer content into useful section headings (H2).', 'warning');
        }

        if ($needsSentenceReview) {
            $issues[] = $this->issue(
                'content_long_sentences',
                $locale === 'bn'
                    ? 'Shorten a few long Bangla sentences so the page is easier to scan.'
                    : 'Shorten a few long English sentences so the page is easier to scan.',
                'warning'
            );
        }

        if ($longParagraphCount > 0) {
            $issues[] = $this->issue(
                'content_long_paragraphs',
                $locale === 'bn'
                    ? 'Split long Bangla paragraphs into smaller ideas.'
                    : 'Split long English paragraphs into smaller ideas.',
                'warning'
            );
        }

        if ($imageCount > $imagesWithAlt) {
            $missing = $imageCount - $imagesWithAlt;
            $issues[] = $this->issue(
                'content_missing_image_alt',
                $missing . ' content image' . ($missing === 1 ? ' needs' : 's need') . ' useful alternative text.',
                'danger'
            );
        }

        if (count($words) >= 120 && $internalLinks === 0 && in_array($type, ['page', 'event', 'annual_report'], true)) {
            $issues[] = $this->issue('content_missing_internal_link', 'Add a useful link to another relevant page on this website.', 'warning');
        } elseif ($externalLinks >= 3 && $internalLinks === 0) {
            $issues[] = $this->issue('content_external_link_balance', 'The page links out several times but does not guide visitors to related website content.', 'warning');
        }

        if ($focusPhrase !== '' && !$focusInHeadings) {
            $issues[] = $this->issue('focus_missing_headings', 'Use the focus phrase naturally in a saved page heading.', 'warning');
        }
        if ($focusPhrase !== '' && !$focusInBody) {
            $issues[] = $this->issue('focus_missing_body', 'Use the focus phrase naturally in the saved page body.', 'warning');
        }

        return [
            'available' => true,
            'locale' => $locale,
            'locale_label' => $locale === 'bn' ? 'Bangla' : 'English',
            'word_count' => count($words),
            'sentence_count' => count($sentences),
            'average_sentence_words' => $averageSentenceLength,
            'long_sentence_count' => $longSentenceCount,
            'long_paragraph_count' => $longParagraphCount,
            'h1_count' => count($h1),
            'h2_count' => count($h2),
            'image_count' => $imageCount,
            'images_with_alt' => $imagesWithAlt,
            'internal_link_count' => $internalLinks,
            'external_link_count' => $externalLinks,
            'focus_in_headings' => $focusInHeadings,
            'focus_in_body' => $focusInBody,
            'readability' => $needsSentenceReview || $longParagraphCount > 0 ? 'Review long passages' : 'Easy to scan',
            'issues' => $issues,
        ];
    }

    /** @return array<int, mixed> */
    private function modelContentValues(Model $model, string $type): array
    {
        return match ($type) {
            'page' => [$model->getAttribute('sub_title'), $model->getAttribute('description')],
            'category' => [$model->getAttribute('description')],
            'event', 'annual_report' => [$model->getAttribute('sub_title'), $model->getAttribute('description')],
            'donation_cause' => [$model->getAttribute('description')],
            default => [],
        };
    }

    /** @param array<string, mixed> $document */
    private function appendValue(array &$document, mixed $value, string $key): void
    {
        if (!is_scalar($value)) {
            return;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }
        if ($this->containsHtml($value)) {
            $document['html'][] = $value;
        } else {
            $document['text'][] = $value;
            if (in_array($key, self::PARAGRAPH_KEYS, true)) {
                $document['paragraphs'][] = $value;
            }
        }
    }

    /** @param array<string, mixed> $document @param array<string, mixed> $content */
    private function appendStructuredContent(array &$document, array $content, bool $topLevel = false): void
    {
        foreach ($content as $key => $value) {
            $key = strtolower((string) $key);
            if (is_array($value)) {
                $this->appendStructuredContent($document, $value, false);
                continue;
            }
            if (!is_scalar($value) || !in_array($key, self::TEXT_KEYS, true)) {
                continue;
            }
            $this->appendValue($document, $value, $key);
            if (!$topLevel && in_array($key, ['heading', 'title', 'name', 'question'], true)) {
                $heading = $this->plainText((string) $value);
                if ($heading !== '') {
                    // Item headings are usually H3 in the visitor component.
                    // Include their wording in focus-phrase coverage without
                    // inflating the document-level H2 count.
                    $document['text'][] = $heading;
                }
            }
        }
    }

    /** @param array<string, mixed> $content */
    private function blockHeading(array $content): string
    {
        $heading = trim((string) ($content['heading'] ?? ''));
        if ($heading !== '') {
            return $this->plainText($heading);
        }

        foreach ((array) ($content['slides'] ?? []) as $slide) {
            if (is_array($slide) && filled($slide['heading'] ?? null)) {
                return $this->plainText((string) $slide['heading']);
            }
        }

        return '';
    }

    /**
     * @param array<int, string> $h1
     * @param array<int, string> $h2
     * @param array<int, string> $text
     * @param array<int, string> $paragraphs
     * @param array<int, array{src: string, alt: string}> $images
     * @param array<int, string> $links
     */
    private function appendHtmlSignals(
        string $html,
        array &$h1,
        array &$h2,
        array &$text,
        array &$paragraphs,
        array &$images,
        array &$links
    ): void {
        foreach (['h1' => &$h1, 'h2' => &$h2] as $tag => &$headings) {
            if (preg_match_all('/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/isu', $html, $matches)) {
                foreach ($matches[1] as $heading) {
                    $headings[] = $this->plainText((string) $heading);
                }
            }
        }
        unset($headings);

        if (preg_match_all('/<p\b[^>]*>(.*?)<\/p>/isu', $html, $matches)) {
            foreach ($matches[1] as $paragraph) {
                $paragraphs[] = $this->plainText((string) $paragraph);
            }
        }

        if (preg_match_all('/<img\b([^>]*)>/isu', $html, $matches)) {
            foreach ($matches[1] as $attributes) {
                $images[] = [
                    'src' => $this->attribute((string) $attributes, 'src'),
                    'alt' => $this->attribute((string) $attributes, 'alt'),
                ];
            }
        }

        if (preg_match_all('/<a\b[^>]*href\s*=\s*(["\'])(.*?)\1/isu', $html, $matches)) {
            foreach ($matches[2] as $href) {
                $links[] = html_entity_decode((string) $href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        $plain = $this->plainText($html);
        if ($plain !== '') {
            $text[] = $plain;
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, array{src: string, alt: string}> $images
     * @param array<int, string> $links
     */
    private function appendArrayMediaAndLinks(array $node, array &$images, array &$links): void
    {
        foreach ($node as $key => $value) {
            $key = strtolower((string) $key);
            if (is_array($value)) {
                $this->appendArrayMediaAndLinks($value, $images, $links);
                continue;
            }
            if (!is_scalar($value) || trim((string) $value) === '') {
                continue;
            }

            if (in_array($key, ['image', 'photo', 'thumbnail', 'logo'], true)) {
                $images[] = [
                    'src' => trim((string) $value),
                    'alt' => trim((string) ($node['image_alt'] ?? $node['alt_text'] ?? $node['alt'] ?? $node['heading'] ?? $node['title'] ?? $node['name'] ?? '')),
                ];
                continue;
            }

            if (($key === 'url' || $key === 'href' || str_ends_with($key, '_url'))
                && !in_array($key, ['image_url', 'video_url', 'youtube_url', 'poster_url', 'file_url'], true)) {
                $links[] = trim((string) $value);
            }
        }
    }

    /** @return array<int, string> */
    private function cleanStrings(array $values): array
    {
        return collect($values)
            ->filter(fn ($value): bool => is_scalar($value))
            ->map(fn ($value): string => $this->plainText((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    private function containsHtml(string $value): bool
    {
        return preg_match('/<\/?[a-z][^>]*>/iu', $value) === 1;
    }

    private function plainText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x{00A0}\s]+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /** @return array<int, string> */
    private function words(string $value): array
    {
        // Combining marks are part of the preceding Bangla letter. Counting
        // them separately would make Bangla sentences appear artificially
        // twice as long as equivalent English sentences.
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{M}\p{N}]*(?:[\x{2019}\'\-][\p{L}\p{M}\p{N}]+)*/u', $value, $matches);

        return $matches[0] ?? [];
    }

    /** @return array<int, string> */
    private function sentences(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return collect(preg_split('/[.!?\x{0964}\x{0965}]+/u', $value) ?: [])
            ->map(fn (string $sentence): string => trim($sentence))
            ->filter(fn (string $sentence): bool => count($this->words($sentence)) > 0)
            ->values()
            ->all();
    }

    private function classifyLink(string $href, string $defaultUrl): ?string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '' || str_starts_with($href, '#') || preg_match('/^(?:mailto|tel|javascript):/i', $href)) {
            return null;
        }
        if (str_starts_with($href, '/') || !preg_match('#^https?://#i', $href)) {
            return 'internal';
        }

        $hrefHost = strtolower((string) parse_url($href, PHP_URL_HOST));
        $siteHost = strtolower((string) parse_url($defaultUrl ?: (string) config('app.url'), PHP_URL_HOST));

        return $hrefHost !== '' && $hrefHost === $siteHost ? 'internal' : 'external';
    }

    private function attribute(string $attributes, string $name): string
    {
        if (!preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*(["\'])(.*?)\1/isu', $attributes, $match)) {
            return '';
        }

        return trim(html_entity_decode((string) $match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function normalizeForMatch(string $value): string
    {
        return mb_strtolower(preg_replace('/[\x{00A0}\s]+/u', ' ', $this->plainText($value)) ?? $value);
    }

    /** @return array{key: string, label: string, tone: string, level: string} */
    private function issue(string $key, string $label, string $tone): array
    {
        $level = $tone === 'danger' ? 'required' : 'recommended';

        return compact('key', 'label', 'tone', 'level');
    }

    /** @return array<string, mixed> */
    private function unavailable(string $locale): array
    {
        return [
            'available' => false,
            'locale' => strtolower($locale) === 'bn' ? 'bn' : 'en',
            'issues' => [],
        ];
    }
}
