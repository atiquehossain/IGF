<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoAuditRun extends Model
{
    public const COMPLETED_STATUSES = ['completed', 'completed_limited'];

    protected $fillable = [
        'status',
        'trigger',
        'triggered_by',
        'started_at',
        'completed_at',
        'urls_checked',
        'issues_found',
        'summary',
        'failure_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'urls_checked' => 'integer',
        'issues_found' => 'integer',
        'summary' => 'array',
    ];

    public function issues(): HasMany
    {
        return $this->hasMany(SeoAuditIssue::class, 'run_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(SeoAuditAlert::class, 'run_id');
    }

    public function isCompletedSnapshot(): bool
    {
        return in_array($this->status, self::COMPLETED_STATUSES, true);
    }

    public function previousCompletedSnapshot(): ?self
    {
        return self::query()
            ->where('id', '<', $this->id)
            ->whereIn('status', self::COMPLETED_STATUSES)
            ->latest('id')
            ->first();
    }

    /**
     * Compare stable issue fingerprints. The crawler is bounded, so these
     * arrays can never exceed the configured per-run finding ceiling.
     *
     * @return array{
     *   has_baseline:bool,
     *   previous_run_id:?int,
     *   new:int,
     *   recurring:int,
     *   resolved:int,
     *   new_high:int,
     *   new_fingerprints:list<string>,
     *   recurring_fingerprints:list<string>,
     *   resolved_fingerprints:list<string>
     * }
     */
    public function comparisonWithPrevious(?self $previous = null): array
    {
        if (!$this->isCompletedSnapshot()) {
            return [
                'has_baseline' => false,
                'previous_run_id' => null,
                'new' => 0,
                'recurring' => 0,
                'resolved' => 0,
                'new_high' => 0,
                'new_fingerprints' => [],
                'recurring_fingerprints' => [],
                'resolved_fingerprints' => [],
            ];
        }

        $previous ??= $this->previousCompletedSnapshot();
        $current = $this->issues()->pluck('severity', 'fingerprint')->all();
        $before = $previous?->issues()->pluck('severity', 'fingerprint')->all() ?? [];
        $newFingerprints = array_values(array_diff(array_keys($current), array_keys($before)));
        $recurringFingerprints = array_values(array_intersect(array_keys($current), array_keys($before)));
        $resolvedFingerprints = array_values(array_diff(array_keys($before), array_keys($current)));

        return [
            'has_baseline' => $previous !== null,
            'previous_run_id' => $previous?->id,
            'new' => count($newFingerprints),
            'recurring' => count($recurringFingerprints),
            'resolved' => count($resolvedFingerprints),
            'new_high' => count(array_filter(
                $newFingerprints,
                fn (string $fingerprint): bool => ($current[$fingerprint] ?? null) === 'high'
            )),
            'new_fingerprints' => $newFingerprints,
            'recurring_fingerprints' => $recurringFingerprints,
            'resolved_fingerprints' => $resolvedFingerprints,
        ];
    }
}
