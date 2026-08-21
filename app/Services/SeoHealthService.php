<?php

namespace App\Services;

class SeoHealthService
{
    /**
     * @param array{title?: string, description?: string, focus_keyword?: string, image?: string, canonical?: string, default_url?: string, indexable?: bool, excluded?: bool} $values
     * @return array{score: int, status: string, issues: array<int, array{key: string, label: string, tone: string, level: string}>, required_count: int, recommended_count: int}
     */
    public function evaluate(array $values): array
    {
        $title = trim((string) ($values['title'] ?? ''));
        $description = trim((string) ($values['description'] ?? ''));
        $focusKeyword = mb_strtolower(trim((string) ($values['focus_keyword'] ?? '')));
        $image = trim((string) ($values['image'] ?? ''));
        $canonical = trim((string) ($values['canonical'] ?? ''));
        $defaultUrl = trim((string) ($values['default_url'] ?? ''));
        $indexable = (bool) ($values['indexable'] ?? true);
        $excluded = (bool) ($values['excluded'] ?? false);
        $issues = [];
        $score = 0;

        if ($title === '') {
            $issues[] = $this->issue('missing_title', 'Add a search title', 'danger');
        } else {
            $score += 30;
            if (mb_strlen($title) > 60) {
                $issues[] = $this->issue('long_title', 'Search title may be cut off', 'warning');
            }
        }

        if ($description === '') {
            $issues[] = $this->issue('missing_description', 'Add a search description', 'danger');
        } else {
            $score += 30;
            if (mb_strlen($description) > 160) {
                $issues[] = $this->issue('long_description', 'Description may be cut off', 'warning');
            } elseif (mb_strlen($description) < 70) {
                $issues[] = $this->issue('short_description', 'Description could explain more', 'warning');
            }
        }

        if ($image === '') {
            $issues[] = $this->issue('missing_image', 'Choose a social sharing image', 'warning');
        } else {
            $score += 20;
        }

        if (!$indexable) {
            $issues[] = $this->issue('hidden', 'Hidden from search engines', 'neutral');
        } else {
            $score += 20;
        }

        if (!$indexable && !$excluded) {
            $issues[] = $this->issue('visibility_conflict', 'Hidden page is still in the sitemap', 'danger');
        }

        if ($canonical !== '' && $defaultUrl !== '' && $this->host($canonical) !== $this->host($defaultUrl)) {
            $issues[] = $this->issue('external_canonical', 'Canonical points to another website', 'warning');
        }

        if ($focusKeyword !== '') {
            if (!str_contains(mb_strtolower($title), $focusKeyword)) {
                $issues[] = $this->issue('focus_missing_title', 'Focus phrase is missing from the title', 'warning');
                $score -= 5;
            }
            if (!str_contains(mb_strtolower($description), $focusKeyword)) {
                $issues[] = $this->issue('focus_missing_description', 'Focus phrase is missing from the description', 'warning');
                $score -= 5;
            }
        }

        $score = max(0, min(100, $score));
        $actionable = collect($issues)->contains(
            fn (array $issue) => in_array($issue['level'], ['required', 'recommended'], true)
        );
        $requiredCount = collect($issues)->where('level', 'required')->count();
        $recommendedCount = collect($issues)->where('level', 'recommended')->count();

        return [
            'score' => $score,
            // A green Ready badge is a promise that there are no outstanding
            // editor actions. Scores remain useful progress indicators, but a
            // 100% score must never hide a recommendation such as a title that
            // will be truncated in search results.
            'status' => !$indexable ? 'Hidden' : ($actionable ? 'Needs attention' : 'Ready'),
            'issues' => $issues,
            'required_count' => $requiredCount,
            'recommended_count' => $recommendedCount,
        ];
    }

    /** @return array{key: string, label: string, tone: string, level: string} */
    public function issue(string $key, string $label, string $tone = 'warning', ?string $level = null): array
    {
        $level ??= match ($tone) {
            'danger' => 'required',
            'neutral' => 'information',
            default => 'recommended',
        };

        return compact('key', 'label', 'tone', 'level');
    }

    private function host(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }
}
