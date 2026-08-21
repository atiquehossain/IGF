<?php

namespace App\Services;

use App\Models\SeoMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class SeoMetadataEditorVersionService
{
    public const CONFLICT_MESSAGE = 'These search and sharing settings changed in another editor. Your unsaved work is still here; reload the page, review the latest version, and apply your changes again.';

    /** @return array{kind: 'model', seoable_type: class-string<Model>, seoable_id: string, locale: string} */
    public function modelIdentity(Model $model, string $locale): array
    {
        return [
            'kind' => 'model',
            'seoable_type' => $model::class,
            'seoable_id' => (string) $model->getKey(),
            'locale' => $locale,
        ];
    }

    /** @return array{kind: 'route', route_name: string, locale: string} */
    public function routeIdentity(string $routeName, string $locale): array
    {
        return [
            'kind' => 'route',
            'route_name' => $routeName,
            'locale' => $locale,
        ];
    }

    public function currentForModel(Model $model, string $locale): string
    {
        // Match the complete row that mutations later re-read under lock.
        // Models returned directly from create() can omit database-default
        // columns from their in-memory original state.
        $persisted = $model->newQuery()->whereKey($model->getKey())->first() ?: $model;
        $metadata = SeoMetadata::withTrashed()
            ->where('seoable_type', $persisted::class)
            ->where('seoable_id', $persisted->getKey())
            ->where('locale', $locale)
            ->first();

        return $this->forModelSnapshot($persisted, $locale, $metadata);
    }

    public function currentForRoute(string $routeName, string $routePath, string $locale): string
    {
        $metadata = SeoMetadata::withTrashed()
            ->where('route_name', $routeName)
            ->where('locale', $locale)
            ->first();

        return $this->forRouteSnapshot($routeName, $routePath, $locale, $metadata);
    }

    /**
     * Bind a UI token to the exact owner and metadata snapshots whose fields
     * are rendered. Callers must not replace either snapshot with a later
     * convenience re-read, otherwise an old form can inherit a newer token.
     */
    public function forModelSnapshot(Model $model, string $locale, ?SeoMetadata $metadata): string
    {
        return $this->token(
            $metadata,
            $this->modelIdentity($model, $locale),
            $this->modelFingerprint($model)
        );
    }

    /** Bind a route UI token to the metadata snapshot displayed in the form. */
    public function forRouteSnapshot(
        string $routeName,
        string $routePath,
        string $locale,
        ?SeoMetadata $metadata
    ): string {
        return $this->token(
            $metadata,
            $this->routeIdentity($routeName, $locale),
            $this->routeFingerprint($routePath)
        );
    }

    public function modelFingerprint(Model $model): string
    {
        $attributes = $model->getRawOriginal();
        ksort($attributes);

        return hash('sha256', serialize([$model::class, (string) $model->getKey(), $attributes]));
    }

    public function routeFingerprint(string $routePath): string
    {
        return hash('sha256', 'route-path:' . $routePath);
    }

    /**
     * Lock every existing SEO row in primary-key order and validate every
     * asserted UI fingerprint before the caller performs any write. Missing
     * identities deliberately remain represented by null; the ownership
     * unique indexes arbitrate the final concurrent-insert race.
     *
     * @param iterable<int, array{identity: array<string, string>, context: string, expected?: mixed, assert?: bool}> $claims
     * @return array<string, SeoMetadata|null>
     */
    public function lockAndAssertMany(iterable $claims): array
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('SEO editor-version locks require an active database transaction.');
        }

        $ordered = collect($claims)
            ->map(function (array $claim): array {
                $claim['key'] = $this->identityKey($claim['identity']);

                return $claim;
            })
            ->sortBy('key')
            ->values();

        abort_if(
            $ordered->pluck('key')->duplicates()->isNotEmpty(),
            409,
            'The same search metadata row was submitted more than once.'
        );

        if ($ordered->isEmpty()) {
            return [];
        }

        $query = SeoMetadata::withTrashed()->where(function ($query) use ($ordered): void {
            foreach ($ordered as $index => $claim) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $identity = $claim['identity'];
                $query->{$method}(function ($nested) use ($identity): void {
                    if ($identity['kind'] === 'route') {
                        $nested->where('route_name', $identity['route_name'])
                            ->where('locale', $identity['locale']);
                    } else {
                        $nested->where('seoable_type', $identity['seoable_type'])
                            ->where('seoable_id', $identity['seoable_id'])
                            ->where('locale', $identity['locale']);
                    }
                });
            }
        });

        $locked = $query->orderBy('id')->lockForUpdate()->get()->keyBy(function (SeoMetadata $metadata): string {
            $identity = filled($metadata->route_name)
                ? $this->routeIdentity((string) $metadata->route_name, (string) $metadata->locale)
                : [
                    'kind' => 'model',
                    'seoable_type' => (string) $metadata->seoable_type,
                    'seoable_id' => (string) $metadata->seoable_id,
                    'locale' => (string) $metadata->locale,
                ];

            return $this->identityKey($identity);
        });
        $result = [];

        foreach ($ordered as $claim) {
            $metadata = $locked->get($claim['key']);
            if ($claim['assert'] ?? true) {
                $this->assertExpectedLocked(
                    $metadata,
                    $claim['identity'],
                    $claim['context'],
                    $claim['expected'] ?? null
                );
            }
            $result[$claim['key']] = $metadata;
        }

        return $result;
    }

    /** @param array<string, string> $identity */
    public function assertExpectedLocked(?SeoMetadata $metadata, array $identity, string $context, mixed $expected): void
    {
        abort_if(!is_string($expected) || trim($expected) === '', 409, self::CONFLICT_MESSAGE);
        $current = $this->token($metadata, $identity, $context);
        abort_unless(hash_equals($current, $expected), 409, self::CONFLICT_MESSAGE);
    }

    /** @param array<string, string> $identity */
    public function key(array $identity): string
    {
        return $this->identityKey($identity);
    }

    public function isOwnershipCollision(QueryException $exception): bool
    {
        $state = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $message = strtolower($exception->getMessage());
        $ownership = str_contains($message, 'seo_metadata_route_locale_unique')
            || str_contains($message, 'seo_metadata_owner_locale_unique')
            || str_contains($message, 'seo_metadata.route_name, seo_metadata.locale')
            || str_contains($message, 'seo_metadata.seoable_type, seo_metadata.seoable_id, seo_metadata.locale');

        return in_array($state, ['23000', '23505'], true) && $ownership;
    }

    /** @param array<string, string> $identity */
    private function token(?SeoMetadata $metadata, array $identity, string $context): string
    {
        $generation = $metadata ? 'v' . (int) $metadata->editor_version : 'missing';
        $state = $metadata
            ? ['id' => (string) $metadata->getKey(), 'version' => (int) $metadata->editor_version, 'trashed' => $metadata->trashed()]
            : ['missing' => true];
        $digest = hash('sha256', serialize([$this->identityKey($identity), $state, $context]));

        return 'seo1.' . $generation . '.' . $digest;
    }

    /** @param array<string, string> $identity */
    private function identityKey(array $identity): string
    {
        if (($identity['kind'] ?? null) === 'route') {
            return implode("\0", ['route', (string) ($identity['route_name'] ?? ''), (string) ($identity['locale'] ?? '')]);
        }

        return implode("\0", [
            'model',
            (string) ($identity['seoable_type'] ?? ''),
            (string) ($identity['seoable_id'] ?? ''),
            (string) ($identity['locale'] ?? ''),
        ]);
    }
}
