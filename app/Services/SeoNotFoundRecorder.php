<?php

namespace App\Services;

use App\Models\SeoNotFoundHit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SeoNotFoundRecorder
{
    public function __construct(private TechnicalSeoPathNormalizer $paths)
    {
    }

    public function record(Request $request): void
    {
        $path = $this->paths->normalize($request->getPathInfo());
        $locale = preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', app()->getLocale())
            ? app()->getLocale()
            : (string) config('app.fallback_locale', 'en');
        $scopeHash = hash('sha256', $locale . '|' . $path);
        $now = now();
        $values = [
            'scope_hash' => $scopeHash,
            'path_hash' => hash('sha256', $path),
            'path' => $path,
            'locale' => $locale,
            'referrer_path' => $this->paths->sameOriginReferrer($request),
            'hits' => 1,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $updated = DB::table('seo_not_found_hits')->where('scope_hash', $scopeHash)->update([
            'hits' => DB::raw('hits + 1'),
            'referrer_path' => $values['referrer_path'],
            'last_seen_at' => $now,
            'resolved_at' => null,
            'updated_at' => $now,
        ]);
        if ($updated > 0) {
            return;
        }

        $rowLimit = max(100, min(100000, (int) config('technical-seo.max_not_found_rows', 10000)));
        if (SeoNotFoundHit::query()->count() >= $rowLimit) {
            return;
        }

        try {
            SeoNotFoundHit::query()->create($values);
        } catch (QueryException) {
            DB::table('seo_not_found_hits')->where('scope_hash', $scopeHash)->update([
                'hits' => DB::raw('hits + 1'),
                'referrer_path' => $values['referrer_path'],
                'last_seen_at' => $now,
                'resolved_at' => null,
                'updated_at' => $now,
            ]);
        }
    }
}
