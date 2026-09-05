<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONTACT_UUID = '79000000-0000-4000-8000-000000000001';

    public function up(): void
    {
        if (! Schema::hasTable('page_menus')) {
            return;
        }

        foreach (['en' => 'Contact', 'bn' => 'যোগাযোগ'] as $locale => $fallbackLabel) {
            // Existing active, draft, or deleted items represent an editorial
            // decision. Never recreate a default over that location.
            if (DB::table('page_menus')->where('type', 'utility')->where('language', $locale)->exists()) {
                continue;
            }

            DB::table('page_menus')->insert([
                'uuid' => self::CONTACT_UUID,
                'name' => $this->setting('contact_label', $locale, $fallbackLabel),
                'description' => null,
                'parent_id' => null,
                'type' => 'utility',
                'link' => 'custom',
                'slug' => $this->safeDestination($this->setting('contact_url', $locale, '/contact-us')),
                'language' => $locale,
                'order_by' => 0,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('page_menus')) {
            return;
        }

        DB::table('page_menus')
            ->where('type', 'utility')
            ->where('uuid', self::CONTACT_UUID)
            ->delete();
    }

    private function setting(string $key, string $locale, string $fallback): string
    {
        if (! Schema::hasTable('site_settings')) {
            return $fallback;
        }

        $value = DB::table('site_settings')
            ->where('group', 'header')
            ->where('key', $key)
            ->whereNull('deleted_at')
            ->whereIn('locale', [$locale, '*'])
            ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$locale])
            ->value('value');

        return trim((string) $value) !== '' ? trim((string) $value) : $fallback;
    }

    private function safeDestination(string $value): string
    {
        $value = trim($value);

        if (str_contains($value, '\\') || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
            return '/contact-us';
        }
        if (str_starts_with($value, '//')) {
            return 'https:'.$value;
        }
        if (str_starts_with($value, '/')) {
            return $value;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true) ? $value : '/contact-us';
    }
};
