<?php

namespace App\Services;

use App\Models\SeoRedirect;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SeoRedirectService
{
    public const SAFE_STATUS_CODES = [301, 302, 307, 308];

    private const INPUT_FIELDS = [
        'from_path',
        'to_url',
        'status_code',
        'is_active',
        'locale',
    ];

    private const PROTECTED_EXACT_SOURCES = [
        '/',
        '/robots.txt',
        '/sitemap.xml',
        '/favicon.ico',
        '/manifest.json',
        '/site.webmanifest',
        '/sw.js',
    ];

    private const PROTECTED_SEGMENT_PREFIXES = [
        '/admin',
        '/api',
        '/assets',
        '/asset',
        '/build',
        '/css',
        '/fonts',
        '/image',
        '/images',
        '/js',
        '/storage',
        '/vendor',
        '/chat',
        '/language',
        '/register',
        '/logout',
        '/change-password',
        '/donation/payment',
        '/donate/payment',
        '/annual-report/download',
        '/notice/download',
        '/notice/pdfviewer',
    ];

    private const PROTECTED_AUTH_PREFIXES = [
        '/login',
        '/password',
        '/verification',
        '/email/verify',
    ];

    public function __construct(private SeoManagedDestinationService $destinations)
    {
    }

    public function create(array $input, ?int $actorId = null): SeoRedirect
    {
        return DB::transaction(function () use ($input, $actorId): SeoRedirect {
            $this->lockRedirectGraph();
            $attributes = $this->completeAttributes($input);
            $source = $this->normalizeSourcePath($attributes['from_path']);
            $sourceHash = $this->sourceHash($source);
            $locale = $this->normalizeLocale($attributes['locale'] ?? null);
            $scopeHash = $this->scopeHash($sourceHash, $locale);

            if (SeoRedirect::withTrashed()->where('source_scope_hash', $scopeHash)->exists()) {
                throw ValidationException::withMessages([
                    'from_path' => 'A redirect for this source and language scope already exists. Restore or edit that redirect instead.',
                ]);
            }

            $redirect = new SeoRedirect();
            $redirect->fill($attributes);
            $redirect->forceFill([
                'created_by' => $this->actorId($actorId),
                'updated_by' => $this->actorId($actorId),
            ]);

            $this->saveOrReportConflict($redirect);

            return $redirect->fresh();
        });
    }

    public function update(SeoRedirect $redirect, array $input, ?int $actorId = null): SeoRedirect
    {
        return DB::transaction(function () use ($redirect, $input, $actorId): SeoRedirect {
            $this->lockRedirectGraph();
            $locked = SeoRedirect::withTrashed()->lockForUpdate()->findOrFail($redirect->getKey());
            abort_if($locked->trashed(), 409, 'Restore this redirect before editing it.');

            $attributes = array_merge([
                'from_path' => $locked->from_path,
                'to_url' => $locked->to_url,
                'status_code' => $locked->status_code,
                'is_active' => $locked->is_active,
                'locale' => $locked->locale,
            ], Arr::only($input, self::INPUT_FIELDS));

            $locked->fill($attributes);
            $locked->forceFill(['updated_by' => $this->actorId($actorId)]);
            $this->saveOrReportConflict($locked);

            return $locked->fresh();
        });
    }

    public function setActive(SeoRedirect $redirect, bool $active, ?int $actorId = null): SeoRedirect
    {
        return DB::transaction(function () use ($redirect, $active, $actorId): SeoRedirect {
            $this->lockRedirectGraph();
            $locked = SeoRedirect::withTrashed()->lockForUpdate()->findOrFail($redirect->getKey());
            abort_if($locked->trashed(), 409, 'Restore this redirect before changing its status.');

            $locked->forceFill([
                'is_active' => $active,
                'updated_by' => $this->actorId($actorId),
            ]);

            if ($active) {
                $this->saveOrReportConflict($locked);
            } else {
                // Unsafe historical redirects must always remain disableable.
                $locked->saveQuietly();
            }

            return $locked->fresh();
        });
    }

    public function delete(SeoRedirect $redirect, ?int $actorId = null): void
    {
        DB::transaction(function () use ($redirect, $actorId): void {
            $this->lockRedirectGraph();
            $locked = SeoRedirect::query()->lockForUpdate()->findOrFail($redirect->getKey());
            $actor = $this->actorId($actorId);
            $locked->forceFill([
                'is_active' => false,
                'deleted_by' => $actor,
                'updated_by' => $actor,
            ])->saveQuietly();
            $locked->delete();
        });
    }

    public function restore(int|SeoRedirect $redirect, ?int $actorId = null): SeoRedirect
    {
        $id = $redirect instanceof SeoRedirect ? $redirect->getKey() : $redirect;

        return DB::transaction(function () use ($id, $actorId): SeoRedirect {
            $this->lockRedirectGraph();
            $locked = SeoRedirect::withTrashed()->lockForUpdate()->findOrFail($id);
            abort_unless($locked->trashed(), 409, 'This redirect is not deleted.');
            $actor = $this->actorId($actorId);

            // Restores are intentionally inactive; activation is a separate,
            // policy-checked lifecycle action.
            $locked->forceFill([
                'is_active' => false,
                'restored_by' => $actor,
                'restored_at' => now(),
                'updated_by' => $actor,
            ]);
            $locked->restoreQuietly();
            $locked->saveQuietly();

            return $locked->fresh();
        });
    }

    /**
     * Enforces policy for model writes, including legacy controller paths.
     */
    public function prepareForPersistence(SeoRedirect $redirect): void
    {
        $source = $this->normalizeSourcePath((string) $redirect->from_path);
        $target = $this->normalizeTargetUrl((string) $redirect->to_url);
        $locale = $this->normalizeLocale($redirect->getAttribute('locale'));
        $rawStatus = $redirect->getAttributes()['status_code'] ?? 301;
        $rawActive = $redirect->getAttributes()['is_active'] ?? true;
        if ((!is_int($rawStatus) && !(is_string($rawStatus) && ctype_digit($rawStatus)))
            || !in_array($rawActive, [true, false, 0, 1, '0', '1'], true)) {
            throw ValidationException::withMessages([
                !in_array($rawActive, [true, false, 0, 1, '0', '1'], true) ? 'is_active' : 'status_code' => 'The redirect status and active state must use supported values.',
            ]);
        }
        $status = (int) $rawStatus;
        $active = in_array($rawActive, [true, 1, '1'], true);

        if (!in_array($status, self::SAFE_STATUS_CODES, true)) {
            throw ValidationException::withMessages([
                'status_code' => 'The redirect status must be 301, 302, 307 or 308.',
            ]);
        }

        $targetPath = $this->targetPath($target);
        if ($targetPath === $source) {
            throw ValidationException::withMessages([
                'to_url' => 'A redirect cannot point back to its own source path.',
            ]);
        }

        $this->assertAllowedTargetPath($targetPath);

        if ($active) {
            if ($this->destinations->isManaged($source, $locale)) {
                throw ValidationException::withMessages([
                    'from_path' => 'This source is currently live managed content. Disable or move the content before creating a redirect from its old address.',
                ]);
            }
            $this->assertNoChain($source, $targetPath, $locale, $redirect->exists ? (int) $redirect->getKey() : null);
        }

        $sourceHash = $this->sourceHash($source);

        $redirect->forceFill([
            'from_path' => $source,
            'normalized_from_path' => $source,
            'from_path_hash' => $sourceHash,
            'locale' => $locale,
            'source_scope_hash' => $this->scopeHash($sourceHash, $locale),
            'to_url' => $target,
            'status_code' => $status,
            'is_active' => $active,
        ]);
    }

    public function resolveActiveForPath(string $requestPath, ?string $locale = null): ?SeoRedirect
    {
        try {
            $source = $this->normalizeSourcePath($requestPath);
            $locale = $this->normalizeLocale($locale);
            $query = SeoRedirect::query()
                ->where('from_path_hash', $this->sourceHash($source))
                ->where('is_active', true);

            if ($locale === null) {
                $query->whereNull('locale');
            } else {
                $query->where(fn ($builder) => $builder->where('locale', $locale)->orWhereNull('locale'))
                    ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$locale]);
            }

            $redirect = $query->first();

            if (!$redirect) {
                return null;
            }

            $this->prepareForPersistence($redirect);

            return $redirect;
        } catch (ValidationException|QueryException) {
            // Invalid historical rows and pre-migration deployments fail open
            // to the real route, never to an unsafe redirect.
            return null;
        }
    }

    public function recordHit(SeoRedirect $redirect): void
    {
        DB::table($redirect->getTable())
            ->where('id', $redirect->getKey())
            ->whereNull('deleted_at')
            ->update([
                'hits' => DB::raw('hits + 1'),
                'last_hit_at' => now(),
            ]);
    }

    public function normalizeSourcePath(string $path): string
    {
        $normalized = $this->normalizePathSyntax($path, 'from_path');

        if ($this->isProtectedSource($normalized)) {
            throw ValidationException::withMessages([
                'from_path' => 'Critical, authentication, payment, SEO, API and asset paths cannot be redirected.',
            ]);
        }

        return $normalized;
    }

    public function sourceHash(string $normalizedPath): string
    {
        return hash('sha256', $normalizedPath);
    }

    public function scopeHash(string $sourceHash, ?string $locale): string
    {
        return hash('sha256', $sourceHash . "\0" . ($locale ?: '*'));
    }

    public function normalizeLocale(mixed $locale): ?string
    {
        if (!is_null($locale) && !is_string($locale)) {
            throw ValidationException::withMessages([
                'locale' => 'Choose a supported website language or the global scope.',
            ]);
        }

        if ($locale === null || trim((string) $locale) === '' || trim((string) $locale) === '*') {
            return null;
        }

        $locale = strtolower(trim((string) $locale));
        $allowed = app(LocalizationManager::class)->editorLocales()->pluck('id')->map(
            fn (mixed $id): string => strtolower((string) $id)
        );

        if (!$allowed->contains($locale)) {
            throw ValidationException::withMessages([
                'locale' => 'Choose a supported website language or the global scope.',
            ]);
        }

        return $locale;
    }

    private function normalizeTargetUrl(string $target): string
    {
        $target = trim($target);
        $this->rejectControlCharacters($target, 'to_url');

        if (strlen($target) > 2048) {
            throw ValidationException::withMessages(['to_url' => 'Redirect destinations cannot exceed 2048 bytes.']);
        }

        if ($target === '' || str_starts_with($target, '//')) {
            throw ValidationException::withMessages([
                'to_url' => 'The redirect destination must be a same-origin path or approved HTTPS URL.',
            ]);
        }

        if (str_starts_with($target, '/')) {
            return $this->normalizeRelativeTarget($target);
        }

        $parts = parse_url($target);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw ValidationException::withMessages([
                'to_url' => 'The redirect destination is not a valid URL.',
            ]);
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $port = $parts['port'] ?? $this->defaultPort($scheme);
        $app = parse_url((string) config('app.url'));
        $appScheme = strtolower((string) ($app['scheme'] ?? ''));
        $appHost = strtolower(rtrim((string) ($app['host'] ?? ''), '.'));
        $appPort = $app['port'] ?? $this->defaultPort($appScheme);
        $sameOrigin = $scheme === $appScheme && $host === $appHost && $port === $appPort;

        $pathAndQuery = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        if ($sameOrigin) {
            return $this->normalizeRelativeTarget($pathAndQuery);
        }

        $allowedHosts = config('seo.redirects.allowed_external_hosts', []);
        if ($scheme !== 'https'
            || $port !== 443
            || !config('seo.redirects.allow_external', false)
            || !in_array($host, $allowedHosts, true)) {
            throw ValidationException::withMessages([
                'to_url' => 'Redirect destinations must remain on this site unless an HTTPS host is explicitly allowlisted.',
            ]);
        }

        return $target;
    }

    private function normalizeRelativeTarget(string $target): string
    {
        $parts = parse_url($target);
        if (!is_array($parts) || isset($parts['scheme'], $parts['host']) || isset($parts['fragment'])) {
            throw ValidationException::withMessages([
                'to_url' => 'The redirect destination is not a valid site path.',
            ]);
        }

        $path = $this->normalizePathSyntax((string) ($parts['path'] ?? '/'), 'to_url', false);
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        return $path . $query;
    }

    private function normalizePathSyntax(string $path, string $field, bool $rejectQuery = true): string
    {
        $path = trim($path);
        $this->rejectControlCharacters($path, $field);

        if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            throw ValidationException::withMessages([
                $field => 'Paths must begin with one slash and cannot contain a backslash.',
            ]);
        }
        if ($rejectQuery && (str_contains($path, '?') || str_contains($path, '#'))) {
            throw ValidationException::withMessages([
                $field => 'Redirect source paths cannot contain a query string or fragment.',
            ]);
        }
        if (strlen($path) > 2048) {
            throw ValidationException::withMessages([$field => 'Paths cannot exceed 2048 bytes.']);
        }

        $decoded = rawurldecode($path);
        $this->rejectControlCharacters($decoded, $field);
        if ($rejectQuery && (str_contains($decoded, '?') || str_contains($decoded, '#'))) {
            throw ValidationException::withMessages([
                $field => 'Redirect source paths cannot contain an encoded query string or fragment.',
            ]);
        }
        if (str_contains($decoded, '\\')) {
            throw ValidationException::withMessages([$field => 'Paths cannot contain an encoded backslash.']);
        }

        $segments = explode('/', $decoded);
        if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
            throw ValidationException::withMessages([$field => 'Paths cannot contain dot segments.']);
        }

        $normalized = preg_replace('#/+#', '/', $decoded) ?: '/';
        $normalized = $normalized === '/' ? '/' : rtrim($normalized, '/');

        return $normalized === '' ? '/' : $normalized;
    }

    private function targetPath(string $target): ?string
    {
        if (!str_starts_with($target, '/')) {
            return null;
        }

        $parts = parse_url($target);

        return $this->normalizePathSyntax((string) ($parts['path'] ?? '/'), 'to_url', false);
    }

    private function assertAllowedTargetPath(?string $path): void
    {
        if ($path === null || $path === '/') {
            return;
        }

        if ($this->isProtectedOperationalPath($path)) {
            throw ValidationException::withMessages([
                'to_url' => 'Redirects cannot target authentication, payment, SEO, API or asset endpoints.',
            ]);
        }
    }

    private function assertNoChain(string $source, ?string $targetPath, ?string $locale, ?int $ignoreId): void
    {
        if ($targetPath === null) {
            return;
        }

        $redirects = SeoRedirect::query()
            ->where('is_active', true)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get(['id', 'from_path', 'normalized_from_path', 'to_url', 'locale']);

        foreach ($redirects as $redirect) {
            if (!$this->scopesOverlap($locale, $this->normalizeStoredLocale($redirect->locale))) {
                continue;
            }

            try {
                $existingSource = $this->normalizePathSyntax(
                    (string) ($redirect->normalized_from_path ?: $redirect->from_path),
                    'from_path'
                );
                $existingTarget = $this->targetPath((string) $redirect->to_url);
            } catch (ValidationException) {
                continue;
            }

            if ($existingSource === $targetPath) {
                throw ValidationException::withMessages([
                    'to_url' => 'This destination already redirects. Point directly to its final destination.',
                ]);
            }
            if ($existingTarget === $source) {
                throw ValidationException::withMessages([
                    'from_path' => 'Another active redirect already points here; this change would create a redirect chain or cycle.',
                ]);
            }
        }
    }

    private function isProtectedSource(string $path): bool
    {
        return in_array(strtolower($path), self::PROTECTED_EXACT_SOURCES, true)
            || $this->isProtectedOperationalPath($path);
    }

    private function isProtectedOperationalPath(string $path): bool
    {
        $path = strtolower($path);
        if (in_array($path, [
            '/robots.txt',
            '/favicon.ico',
            '/manifest.json',
            '/site.webmanifest',
            '/sw.js',
        ], true) || str_starts_with($path, '/sitemap')) {
            return true;
        }

        foreach (self::PROTECTED_SEGMENT_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }
        foreach (self::PROTECTED_AUTH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return str_starts_with($path, '/_');
    }

    private function completeAttributes(array $input): array
    {
        $attributes = array_merge([
            'status_code' => 301,
            'is_active' => true,
            'locale' => null,
        ], Arr::only($input, self::INPUT_FIELDS));

        foreach (['from_path', 'to_url'] as $required) {
            if (!array_key_exists($required, $attributes)) {
                throw ValidationException::withMessages([$required => 'This field is required.']);
            }
        }

        return $attributes;
    }

    private function lockRedirectGraph(): Collection
    {
        $mutex = DB::table('seo_redirect_locks')->where('id', 1)->lockForUpdate()->first();
        if (!$mutex) {
            throw new RuntimeException('The SEO redirect graph lock is not initialized.');
        }

        return SeoRedirect::withTrashed()->lockForUpdate()->get();
    }

    private function saveOrReportConflict(SeoRedirect $redirect): void
    {
        try {
            $redirect->save();
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages([
                    'from_path' => 'A redirect for this normalized source and language scope already exists.',
                ]);
            }

            throw $exception;
        }
    }

    private function actorId(?int $actorId): ?int
    {
        return $actorId ?? auth('admin')->id();
    }

    private function defaultPort(string $scheme): ?int
    {
        return match ($scheme) {
            'http' => 80,
            'https' => 443,
            default => null,
        };
    }

    private function rejectControlCharacters(string $value, string $field): void
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw ValidationException::withMessages([$field => 'Control characters are not allowed.']);
        }
    }

    private function scopesOverlap(?string $left, ?string $right): bool
    {
        return $left === null || $right === null || $left === $right;
    }

    private function normalizeStoredLocale(mixed $locale): ?string
    {
        $locale = trim((string) ($locale ?? ''));

        return $locale === '' ? null : strtolower($locale);
    }
}
