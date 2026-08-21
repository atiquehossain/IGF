<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ContactMessage;
use App\Models\Sponsorship;
use App\Models\Subscriber;
use App\Models\Volunteer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class PrivacyRetentionService
{
    public function __construct(private AdminAuditService $audit)
    {
    }

    /** @return array<string, array{enabled:bool,eligible:int,processed:int}> */
    public function run(bool $execute = false): array
    {
        $results = [];
        foreach ($this->policies() as $name => $policy) {
            $days = $this->positiveDays(config("privacy.retention.{$name}.days"));
            if ($days === null) {
                $results[$name] = ['enabled' => false, 'eligible' => 0, 'processed' => 0];
                continue;
            }

            $query = $policy['query'](now()->subDays($days));
            $eligible = (clone $query)->count();
            $processed = $execute
                ? DB::transaction(function () use ($policy, $query, $name, $eligible): int {
                    $processed = $policy['apply']($query);
                    $this->audit->record(null, 'privacy_retention.applied', 'privacy-retention', context: [
                        'policy' => $name,
                        'eligible_count' => $eligible,
                        'processed_count' => $processed,
                    ]);

                    return $processed;
                })
                : 0;
            $results[$name] = ['enabled' => true, 'eligible' => $eligible, 'processed' => $processed];
        }

        return $results;
    }

    private function policies(): array
    {
        $completed = ['completed', 'spam'];

        return [
            'contact_enquiries' => [
                'query' => fn ($cutoff) => ContactMessage::query()
                    ->whereNull('anonymized_at')->whereIn('workflow_status', $completed)
                    ->whereNotNull('resolved_at')->where('resolved_at', '<=', $cutoff),
                'apply' => fn (Builder $query) => $this->anonymize($query, fn (ContactMessage $record) => [
                    'first_name' => 'Anonymized', 'last_name' => null, 'email' => null,
                    'phone' => null, 'address' => null, 'message' => '[Removed by retention policy]',
                    'ip' => null, 'internal_notes' => null, 'follow_up_at' => null, 'anonymized_at' => now(),
                ]),
            ],
            'sponsorship_enquiries' => [
                'query' => fn ($cutoff) => Sponsorship::query()
                    ->whereNull('anonymized_at')->whereIn('workflow_status', $completed)
                    ->whereNotNull('resolved_at')->where('resolved_at', '<=', $cutoff),
                'apply' => fn (Builder $query) => $this->anonymize($query, fn (Sponsorship $record) => [
                    'name' => 'Anonymized supporter',
                    'email' => 'retained-' . $record->id . '@privacy.invalid',
                    'phone' => null, 'address' => null, 'internal_notes' => null,
                    'follow_up_at' => null, 'anonymized_at' => now(),
                ]),
            ],
            'volunteer_applications' => [
                'query' => fn ($cutoff) => Volunteer::query()
                    ->whereNull('anonymized_at')->whereIn('workflow_status', $completed)
                    ->whereNotNull('resolved_at')->where('resolved_at', '<=', $cutoff),
                'apply' => fn (Builder $query) => $this->anonymize($query, fn (Volunteer $record) => [
                    'name' => 'Anonymized volunteer', 'institution' => null, 'email' => null,
                    'phone' => null, 'address' => null, 'internal_notes' => null,
                    'follow_up_at' => null, 'anonymized_at' => now(),
                ]),
            ],
            'closed_chat' => [
                'query' => fn ($cutoff) => ChatConversation::query()
                    ->whereNull('anonymized_at')->where('status', 'closed')
                    ->whereNotNull('closed_at')->where('closed_at', '<=', $cutoff),
                'apply' => fn (Builder $query) => $this->mutateEligible($query, function (ChatConversation $record): void {
                    $record->messages()->update([
                        'body' => '[Removed by approved retention policy]',
                        'user_id' => null,
                    ]);
                    $record->forceFill([
                        'visitor_token_hash' => hash('sha256', random_bytes(32)),
                        'user_id' => null, 'guest_name' => null, 'guest_email' => null,
                        'guest_phone' => null, 'page_url' => null, 'anonymized_at' => now(),
                    ])->saveQuietly();
                }),
            ],
            'subscribers' => [
                'query' => fn ($cutoff) => Subscriber::query()->where('created_at', '<=', $cutoff),
                'apply' => fn (Builder $query) => $this->mutateEligible(
                    $query,
                    fn (Subscriber $record) => $record->delete(),
                    500
                ),
            ],
        ];
    }

    private function anonymize(Builder $query, callable $attributes): int
    {
        return $this->mutateEligible(
            $query,
            fn (Model $record) => $record->forceFill($attributes($record))->saveQuietly()
        );
    }

    /**
     * Re-fetch each candidate under a row lock using the original eligibility
     * query. A record reopened or otherwise changed after chunk hydration is
     * skipped instead of being irreversibly minimized from a stale model.
     */
    private function mutateEligible(Builder $query, callable $mutation, int $chunkSize = 100): int
    {
        $processed = 0;
        $eligibility = clone $query;
        (clone $query)->orderBy('id')->chunkById($chunkSize, function ($records) use ($eligibility, $mutation, &$processed): void {
            foreach ($records as $record) {
                $mutated = DB::transaction(function () use ($eligibility, $record, $mutation): bool {
                    $locked = (clone $eligibility)
                        ->whereKey($record->getKey())
                        ->lockForUpdate()
                        ->first();
                    if (!$locked) {
                        return false;
                    }

                    $mutation($locked);

                    return true;
                });
                if ($mutated) {
                    $processed++;
                }
            }
        });

        return $processed;
    }

    private function positiveDays(mixed $value): ?int
    {
        $days = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 36500],
        ]);

        return $days === false ? null : $days;
    }
}
