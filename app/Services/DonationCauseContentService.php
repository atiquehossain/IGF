<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\DonationCauseAmount;
use App\Models\DonationCauseSection;
use App\Models\DonationType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DonationCauseContentService
{
    public const MAX_AMOUNT_CARDS = 12;

    public const MAX_LANDING_SECTIONS = 12;

    public const MIN_AMOUNT = 10;

    public const MAX_AMOUNT = 500000;

    public const LAYOUT_OPTIONS = [
        'text' => 'donation_content.layout.text',
        'media-left' => 'donation_content.layout.media_left',
        'media-right' => 'donation_content.layout.media_right',
        'highlight' => 'donation_content.layout.highlight',
    ];

    public function __construct(
        private ContentSanitizer $sanitizer,
        private AdminAuditService $audit,
    ) {}

    public function conflictMessage(): string
    {
        return __('donation_content.validation.conflict');
    }

    /**
     * Validate and normalize the content editor payload. Payment providers,
     * checkout limits, and gateway configuration intentionally do not live in
     * this admin-facing contract.
     *
     * @return array{content_editor_version:int, amount_cards: array<int, array<string, mixed>>, landing_sections: array<int, array<string, mixed>>}
     */
    public function validateAdminPayload(array $input): array
    {
        $validator = Validator::make($input, [
            'content_editor_ready' => ['required', 'accepted'],
            'amount_cards_payload_ready' => ['required', 'accepted'],
            'landing_sections_payload_ready' => ['required', 'accepted'],
            'content_editor_version' => ['required', 'integer', 'min:1'],
            'amount_cards' => ['nullable', 'array', 'max:' . self::MAX_AMOUNT_CARDS],
            'amount_cards.*' => ['required', 'array'],
            'amount_cards.*.uuid' => ['nullable', 'uuid', 'distinct'],
            'amount_cards.*.amount' => [
                'required',
                'integer',
                'min:' . self::MIN_AMOUNT,
                'max:' . self::MAX_AMOUNT,
                'distinct',
            ],
            'amount_cards.*.impact' => ['required', 'array'],
            'amount_cards.*.impact.en' => ['required', 'string', 'max:300'],
            'amount_cards.*.impact.bn' => ['nullable', 'string', 'max:300'],
            'amount_cards.*.enabled' => ['required', 'boolean'],

            'landing_sections' => ['nullable', 'array', 'max:' . self::MAX_LANDING_SECTIONS],
            'landing_sections.*' => ['required', 'array'],
            'landing_sections.*.uuid' => ['nullable', 'uuid', 'distinct'],
            'landing_sections.*.layout' => ['required', 'string', Rule::in(array_keys(self::LAYOUT_OPTIONS))],
            'landing_sections.*.title' => ['nullable', 'array'],
            'landing_sections.*.title.en' => ['nullable', 'string', 'max:255'],
            'landing_sections.*.title.bn' => ['nullable', 'string', 'max:255'],
            'landing_sections.*.body' => ['nullable', 'array'],
            'landing_sections.*.body.en' => ['nullable', 'string', 'max:30000'],
            'landing_sections.*.body.bn' => ['nullable', 'string', 'max:30000'],
            'landing_sections.*.image_media_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('media_assets', 'uuid')
                    ->whereNull('deleted_at')
                    ->where(fn ($query) => $query->where('mime_type', 'like', 'image/%')),
            ],
            'landing_sections.*.image_alt' => ['nullable', 'array'],
            'landing_sections.*.image_alt.en' => ['nullable', 'string', 'max:255'],
            'landing_sections.*.image_alt.bn' => ['nullable', 'string', 'max:255'],
            'landing_sections.*.video_media_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('media_assets', 'uuid')
                    ->whereNull('deleted_at')
                    ->where(fn ($query) => $query->where('mime_type', 'like', 'video/%')),
            ],
            'landing_sections.*.video_url' => ['nullable', 'string', 'max:2048'],
            'landing_sections.*.video_title' => ['nullable', 'array'],
            'landing_sections.*.video_title.en' => ['nullable', 'string', 'min:3', 'max:255'],
            'landing_sections.*.video_title.bn' => ['nullable', 'string', 'min:3', 'max:255'],
            'landing_sections.*.video_transcript' => ['nullable', 'array'],
            'landing_sections.*.video_transcript.en' => ['nullable', 'string', 'min:20', 'max:10000'],
            'landing_sections.*.video_transcript.bn' => ['nullable', 'string', 'min:20', 'max:10000'],
            'landing_sections.*.cta_label' => ['nullable', 'array'],
            'landing_sections.*.cta_label.en' => ['nullable', 'string', 'max:120'],
            'landing_sections.*.cta_label.bn' => ['nullable', 'string', 'max:120'],
            'landing_sections.*.cta_url' => ['nullable', 'string', 'max:2048'],
            'landing_sections.*.enabled' => ['required', 'boolean'],
        ], [
            'content_editor_ready.required' => __('donation_content.validation.editor_not_ready'),
            'content_editor_ready.accepted' => __('donation_content.validation.editor_not_ready'),
            'amount_cards_payload_ready.required' => __('donation_content.validation.amounts_not_ready'),
            'amount_cards_payload_ready.accepted' => __('donation_content.validation.amounts_not_ready'),
            'landing_sections_payload_ready.required' => __('donation_content.validation.sections_not_ready'),
            'landing_sections_payload_ready.accepted' => __('donation_content.validation.sections_not_ready'),
            'amount_cards.*.amount.distinct' => __('donation_content.validation.amount_unique'),
            'amount_cards.*.impact.en.required' => __('donation_content.validation.impact_required'),
        ], __('donation_content.validation.attributes'));

        $validator->after(function ($validator) use ($input): void {
            foreach (array_values((array) ($input['landing_sections'] ?? [])) as $index => $section) {
                if (!is_array($section)) {
                    continue;
                }

                $mediaChoices = collect([
                    $section['image_media_uuid'] ?? null,
                    $section['video_media_uuid'] ?? null,
                    trim((string) ($section['video_url'] ?? '')) ?: null,
                ])->filter()->count();
                if ($mediaChoices > 1) {
                    $validator->errors()->add(
                        "landing_sections.$index.image_media_uuid",
                        __('donation_content.validation.one_media_source')
                    );
                }

                $videoUrl = trim((string) ($section['video_url'] ?? ''));
                if ($videoUrl !== '' && $this->videoEmbedUrl($videoUrl) === '') {
                    $validator->errors()->add(
                        "landing_sections.$index.video_url",
                        __('donation_content.validation.video_url')
                    );
                }

                $hasImage = filled($section['image_media_uuid'] ?? null);
                $hasUploadedVideo = filled($section['video_media_uuid'] ?? null);
                $hasVideo = $hasUploadedVideo || $videoUrl !== '';
                $englishImageAlt = $this->plainText(data_get($section, 'image_alt.en', ''));
                $englishContext = collect([
                    $this->plainText(data_get($section, 'title.en', '')),
                    $this->plainText($this->sanitizer->sanitizeHtml((string) data_get($section, 'body.en', ''))),
                    $this->plainText(data_get($section, 'cta_label.en', '')),
                ])->contains(fn (string $value): bool => $value !== '');
                if ($hasImage && !$englishContext && $englishImageAlt === '') {
                    $validator->errors()->add(
                        "landing_sections.$index.image_alt.en",
                        __('donation_content.validation.image_alt')
                    );
                }

                $englishVideoTitle = $this->plainText(data_get($section, 'video_title.en', ''));
                if ($hasVideo && mb_strlen($englishVideoTitle) < 3) {
                    $validator->errors()->add(
                        "landing_sections.$index.video_title.en",
                        __('donation_content.validation.video_title')
                    );
                }

                $englishTranscript = $this->plainText(data_get($section, 'video_transcript.en', ''));
                if ($hasUploadedVideo && mb_strlen($englishTranscript) < 20) {
                    $validator->errors()->add(
                        "landing_sections.$index.video_transcript.en",
                        __('donation_content.validation.video_transcript')
                    );
                }

                $ctaUrl = trim((string) ($section['cta_url'] ?? ''));
                $ctaLabel = trim(strip_tags((string) data_get($section, 'cta_label.en', '')));
                if (($ctaUrl === '') !== ($ctaLabel === '')) {
                    $validator->errors()->add(
                        "landing_sections.$index.cta_url",
                        __('donation_content.validation.cta_pair')
                    );
                } elseif ($ctaUrl !== '' && $this->sanitizer->sanitizeUrl($ctaUrl) === '') {
                    $validator->errors()->add(
                        "landing_sections.$index.cta_url",
                        __('donation_content.validation.cta_url')
                    );
                }

                if (!$this->sectionHasContent($section)) {
                    $validator->errors()->add(
                        "landing_sections.$index.title.en",
                        __('donation_content.validation.section_content')
                    );
                }
            }
        });

        $validated = $validator->validate();

        return [
            'content_editor_version' => (int) $validated['content_editor_version'],
            'amount_cards' => collect($validated['amount_cards'] ?? [])
                ->values()
                ->map(fn (array $card): array => [
                    'uuid' => filled($card['uuid'] ?? null) ? (string) $card['uuid'] : null,
                    'amount' => (int) $card['amount'],
                    'impact' => $this->plainLocalized($card['impact'] ?? []),
                    'enabled' => (bool) $card['enabled'],
                ])->all(),
            'landing_sections' => collect($validated['landing_sections'] ?? [])
                ->values()
                ->map(fn (array $section): array => [
                    'uuid' => filled($section['uuid'] ?? null) ? (string) $section['uuid'] : null,
                    'layout' => (string) $section['layout'],
                    'title' => $this->plainLocalized($section['title'] ?? []),
                    'body' => $this->htmlLocalized($section['body'] ?? []),
                    'image_media_uuid' => filled($section['image_media_uuid'] ?? null)
                        ? (string) $section['image_media_uuid']
                        : null,
                    'image_alt' => $this->plainLocalized($section['image_alt'] ?? []),
                    'video_media_uuid' => filled($section['video_media_uuid'] ?? null)
                        ? (string) $section['video_media_uuid']
                        : null,
                    'video_url' => filled($section['video_url'] ?? null)
                        ? $this->videoEmbedUrl((string) $section['video_url'])
                        : null,
                    'video_title' => $this->plainLocalized($section['video_title'] ?? []),
                    'video_transcript' => $this->plainLocalized($section['video_transcript'] ?? []),
                    'cta_label' => $this->plainLocalized($section['cta_label'] ?? []),
                    'cta_url' => filled($section['cta_url'] ?? null)
                        ? $this->sanitizer->sanitizeUrl($section['cta_url'])
                        : null,
                    'enabled' => (bool) $section['enabled'],
                ])->all(),
        ];
    }

    /**
     * Atomically replace the two small ordered lists while preserving UUIDs
     * for rows that still exist. A UUID from another cause is never adopted.
     */
    public function replace(DonationType $cause, array $payload, ?Admin $actor = null): DonationType
    {
        if (!array_key_exists('content_editor_version', $payload)
            || !array_key_exists('amount_cards', $payload)
            || !array_key_exists('landing_sections', $payload)) {
            throw new \InvalidArgumentException(__('donation_content.errors.incomplete_payload'));
        }

        $expectedVersion = (int) $payload['content_editor_version'];

        try {
            return DB::transaction(function () use ($cause, $payload, $actor, $expectedVersion): DonationType {
                $locked = DonationType::query()->whereKey($cause->getKey())->lockForUpdate()->firstOrFail();
                $currentVersion = (int) $locked->content_editor_version;
                if ($currentVersion !== $expectedVersion) {
                    throw new ConflictHttpException($this->conflictMessage());
                }

                $previousAmountCount = $locked->amountCards()->count();
                $previousSectionCount = $locked->landingSections()->count();
                $this->replaceAmounts($locked, $payload['amount_cards']);
                $this->replaceSections($locked, $payload['landing_sections']);
                $locked->forceFill(['content_editor_version' => $currentVersion + 1])->save();

                $this->audit->record($actor, 'donation_cause.content_updated', $locked, [
                    'content_editor_version' => ['from' => $currentVersion, 'to' => $currentVersion + 1],
                    'amount_cards' => ['from_count' => $previousAmountCount, 'to_count' => count($payload['amount_cards'])],
                    'landing_sections' => ['from_count' => $previousSectionCount, 'to_count' => count($payload['landing_sections'])],
                ]);

                return $locked->fresh(['amountCards', 'landingSections.imageAsset', 'landingSections.videoAsset']);
            });
        } catch (ConflictHttpException $exception) {
            $currentVersion = (int) DonationType::query()
                ->whereKey($cause->getKey())
                ->value('content_editor_version');
            $this->audit->record($actor, 'donation_cause.content_conflict', $cause, [
                'content_editor_version' => ['submitted' => $expectedVersion, 'current' => $currentVersion],
            ], outcome: 'denied');

            throw $exception;
        }
    }

    /** @return array{amount_options: array<int, array<string, mixed>>, landing_sections: array<int, array<string, mixed>>} */
    public function publicPayload(DonationType $cause, string $locale): array
    {
        $locale = $this->localeKey($locale);
        $cause->loadMissing(['amountCards', 'landingSections.imageAsset', 'landingSections.videoAsset']);

        $amounts = $cause->amountCards
            ->where('enabled', true)
            ->take(self::MAX_AMOUNT_CARDS)
            ->map(fn (DonationCauseAmount $card): array => [
                'uuid' => (string) $card->uuid,
                'amount' => (int) $card->amount,
                'impact' => $this->localized($card->impact, $locale),
            ])
            ->values()
            ->all();

        $sections = $cause->landingSections
            ->where('enabled', true)
            ->take(self::MAX_LANDING_SECTIONS)
            ->map(function (DonationCauseSection $section) use ($cause, $locale): array {
                $title = $this->plainText($this->localized($section->title, $locale));
                $body = $this->sanitizer->sanitizeHtml($this->localized($section->body, $locale));
                $imageUrl = $this->sanitizer->sanitizeUrl($section->imageAsset?->url);
                $uploadedVideoUrl = $this->sanitizer->sanitizeUrl($section->videoAsset?->url);
                $embedUrl = $uploadedVideoUrl === '' ? $this->videoEmbedUrl((string) $section->video_url) : '';
                $ctaUrl = $this->sanitizer->sanitizeUrl($section->cta_url);
                $ctaLabel = $this->plainText($this->localized($section->cta_label, $locale));
                $cta = $ctaUrl !== '' && $ctaLabel !== ''
                    ? ['label' => $ctaLabel, 'url' => $ctaUrl]
                    : null;
                $imageAlt = $this->plainText(
                    $this->localized($section->image_alt, $locale)
                    ?: (string) ($section->imageAsset?->alt_text ?? $title)
                );
                $hasVisibleContext = $title !== ''
                    || $this->plainText($body) !== ''
                    || $cta !== null;
                if ($imageUrl !== '' && $imageAlt === '' && !$hasVisibleContext) {
                    // Legacy image-only rows without a usable accessible name
                    // stay out of the public contract until an editor repairs them.
                    $imageUrl = '';
                }

                $videoTitle = $this->plainText($this->localized($section->video_title, $locale));
                if (mb_strlen($videoTitle) < 3) {
                    $videoTitle = $this->plainText($this->localized($section->video_title, 'en'));
                }
                $videoTitle = $videoTitle
                    ?: $title
                    ?: $this->plainText((string) $cause->name . ' video');
                $videoTranscript = $this->plainText($this->localized($section->video_transcript, $locale));
                if (mb_strlen($videoTranscript) < 20) {
                    $videoTranscript = $this->plainText($this->localized($section->video_transcript, 'en'));
                }
                $video = null;
                if ($uploadedVideoUrl !== '' && $videoTitle !== '' && mb_strlen($videoTranscript) >= 20) {
                    $video = [
                        'type' => 'file',
                        'url' => $uploadedVideoUrl,
                        'title' => $videoTitle,
                        'transcript' => $videoTranscript,
                    ];
                } elseif ($embedUrl !== '' && $videoTitle !== '') {
                    $video = [
                        'type' => 'embed',
                        'url' => $embedUrl,
                        'title' => $videoTitle,
                        'transcript' => $videoTranscript,
                    ];
                }

                return [
                    'uuid' => (string) $section->uuid,
                    'layout' => array_key_exists((string) $section->layout, self::LAYOUT_OPTIONS)
                        ? (string) $section->layout
                        : 'text',
                    'title' => $title,
                    'body' => $body,
                    'image' => $imageUrl,
                    'image_alt' => $imageAlt,
                    'video' => $video,
                    'cta' => $cta,
                ];
            })
            ->filter(fn (array $section): bool => $section['title'] !== ''
                || $section['body'] !== ''
                || $section['image'] !== ''
                || $section['video'] !== null
                || $section['cta'] !== null)
            ->values()
            ->all();

        return [
            // An empty list intentionally signals the Vue checkout to use the
            // global website-customizer defaults for legacy and new causes.
            'amount_options' => $amounts,
            'landing_sections' => $sections,
        ];
    }

    public function videoEmbedUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parts = parse_url($value);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])) {
            return '';
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $segments = $path === '' ? [] : explode('/', $path);
        $videoId = '';

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $videoId = (string) ($segments[0] ?? '');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
            if ($path === 'watch') {
                parse_str((string) ($parts['query'] ?? ''), $query);
                $videoId = (string) ($query['v'] ?? '');
            } elseif (in_array((string) ($segments[0] ?? ''), ['embed', 'shorts', 'live'], true)) {
                $videoId = (string) ($segments[1] ?? '');
            }
        } elseif (in_array($host, ['youtube-nocookie.com', 'www.youtube-nocookie.com'], true)
            && (string) ($segments[0] ?? '') === 'embed') {
            $videoId = (string) ($segments[1] ?? '');
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) === 1) {
            return 'https://www.youtube-nocookie.com/embed/' . $videoId;
        }

        $vimeoId = '';
        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true)) {
            $vimeoId = (string) ($segments[0] ?? '');
        } elseif ($host === 'player.vimeo.com' && (string) ($segments[0] ?? '') === 'video') {
            $vimeoId = (string) ($segments[1] ?? '');
        }

        return preg_match('/^[0-9]{6,12}$/', $vimeoId) === 1
            ? 'https://player.vimeo.com/video/' . $vimeoId
            : '';
    }

    private function replaceAmounts(DonationType $cause, array $cards): void
    {
        $existing = $cause->amountCards()->get()->keyBy('uuid');
        $keptIds = [];

        foreach (array_values($cards) as $index => $attributes) {
            $card = $existing->get((string) ($attributes['uuid'] ?? ''))
                ?? new DonationCauseAmount(['uuid' => (string) Str::uuid()]);
            $card->fill([
                'amount' => (int) $attributes['amount'],
                'impact' => $attributes['impact'],
                'display_order' => ($index + 1) * 10,
                'enabled' => (bool) $attributes['enabled'],
            ]);
            $cause->amountCards()->save($card);
            $keptIds[] = $card->getKey();
        }

        $query = $cause->amountCards();
        $keptIds === [] ? $query->delete() : $query->whereNotIn('id', $keptIds)->delete();
    }

    private function replaceSections(DonationType $cause, array $sections): void
    {
        $existing = $cause->landingSections()->get()->keyBy('uuid');
        $keptIds = [];

        foreach (array_values($sections) as $index => $attributes) {
            $section = $existing->get((string) ($attributes['uuid'] ?? ''))
                ?? new DonationCauseSection(['uuid' => (string) Str::uuid()]);
            $section->fill([
                'layout' => $attributes['layout'],
                'title' => $attributes['title'],
                'body' => $attributes['body'],
                'image_media_uuid' => $attributes['image_media_uuid'],
                'image_alt' => $attributes['image_alt'],
                'video_media_uuid' => $attributes['video_media_uuid'],
                'video_url' => $attributes['video_url'],
                'video_title' => $attributes['video_title'],
                'video_transcript' => $attributes['video_transcript'],
                'cta_label' => $attributes['cta_label'],
                'cta_url' => $attributes['cta_url'],
                'display_order' => ($index + 1) * 10,
                'enabled' => (bool) $attributes['enabled'],
            ]);
            $cause->landingSections()->save($section);
            $keptIds[] = $section->getKey();
        }

        $query = $cause->landingSections();
        $keptIds === [] ? $query->delete() : $query->whereNotIn('id', $keptIds)->delete();
    }

    private function sectionHasContent(array $section): bool
    {
        $copy = [
            trim(strip_tags((string) data_get($section, 'title.en', ''))),
            trim(strip_tags((string) data_get($section, 'title.bn', ''))),
            $this->sanitizer->sanitizeHtml((string) data_get($section, 'body.en', '')),
            $this->sanitizer->sanitizeHtml((string) data_get($section, 'body.bn', '')),
            trim(strip_tags((string) data_get($section, 'cta_label.en', ''))),
        ];

        return collect($copy)->contains(fn (string $value): bool => $value !== '')
            || filled($section['image_media_uuid'] ?? null)
            || filled($section['video_media_uuid'] ?? null)
            || trim((string) ($section['video_url'] ?? '')) !== '';
    }

    private function plainLocalized(mixed $values): array
    {
        $values = is_array($values) ? $values : [];

        return [
            'en' => trim(strip_tags((string) ($values['en'] ?? ''))),
            'bn' => trim(strip_tags((string) ($values['bn'] ?? ''))),
        ];
    }

    private function plainText(mixed $value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function htmlLocalized(mixed $values): array
    {
        $values = is_array($values) ? $values : [];

        return [
            'en' => $this->sanitizer->sanitizeHtml((string) ($values['en'] ?? '')),
            'bn' => $this->sanitizer->sanitizeHtml((string) ($values['bn'] ?? '')),
        ];
    }

    private function localized(mixed $values, string $locale): string
    {
        $values = is_array($values) ? $values : [];
        $localized = trim((string) ($values[$locale] ?? ''));

        return $localized !== '' ? $localized : trim((string) ($values['en'] ?? ''));
    }

    private function localeKey(string $locale): string
    {
        return str_starts_with(strtolower($locale), 'bn') ? 'bn' : 'en';
    }
}
