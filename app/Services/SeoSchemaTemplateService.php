<?php

namespace App\Services;

use Illuminate\Support\Arr;

class SeoSchemaTemplateService
{
    /** @return array<string, string> */
    public function options(): array
    {
        return [
            'none' => 'No structured data',
            'webpage' => 'Standard web page',
            'ngo' => 'Nonprofit organization',
            'about' => 'About page',
            'contact' => 'Contact page',
            'collection' => 'Listing / collection page',
            'event' => 'Event',
            'article' => 'Article or publication',
            'donate' => 'Donation page',
        ];
    }

    public function suggestedFor(string $kind, ?string $routeName = null): string
    {
        return match (true) {
            $routeName === 'frontend.home' => 'ngo',
            $routeName === 'frontend.about' => 'about',
            $routeName === 'frontend.contactUs' => 'contact',
            $routeName === 'frontend.donate.index' => 'donate',
            in_array($routeName, ['frontend.events', 'frontend.project', 'frontend.gallery', 'frontend.annual_report.index'], true) => 'collection',
            $kind === 'category' => 'collection',
            $kind === 'event' => 'event',
            default => 'webpage',
        };
    }

    /**
     * @param array{name?: string, url?: string, description?: string, image?: string, locale?: string} $context
     * @return array<string, mixed>
     */
    public function generate(string $template, array $context): array
    {
        if ($template === 'none') {
            return [];
        }

        $type = match ($template) {
            'ngo' => 'NGO',
            'about' => 'AboutPage',
            'contact' => 'ContactPage',
            'collection' => 'CollectionPage',
            'event' => 'Event',
            'article' => 'Article',
            'donate' => 'DonateAction',
            default => 'WebPage',
        };

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => trim((string) Arr::get($context, 'name')),
            'url' => trim((string) Arr::get($context, 'url')),
            'description' => trim((string) Arr::get($context, 'description')),
            'image' => trim((string) Arr::get($context, 'image')),
            'inLanguage' => trim((string) Arr::get($context, 'locale')),
        ];

        if ($template === 'donate') {
            $schema['target'] = $schema['url'];
        }

        return array_filter($schema, static fn ($value) => $value !== '');
    }

    public function detect(?array $schema): string
    {
        $type = strtolower((string) ($schema['@type'] ?? ''));

        return match ($type) {
            '' => 'none',
            'ngo', 'nonprofitorganization' => 'ngo',
            'aboutpage' => 'about',
            'contactpage' => 'contact',
            'collectionpage' => 'collection',
            'event' => 'event',
            'article', 'newsarticle', 'report' => 'article',
            'donateaction' => 'donate',
            'webpage' => 'webpage',
            default => 'expert',
        };
    }
}
