<?php

namespace App\Services;

use App\Models\TranslationLocale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LocalizationManager
{
    public function editorLocales(): Collection
    {
        return collect(config('localization.editor_locales', [
            'en' => ['name' => 'English', 'native_name' => 'English'],
        ]))->map(fn (array $details, string $locale) => (object) [
            'id' => $locale,
            'name' => $details['name'] ?? strtoupper($locale),
            'native_name' => $details['native_name'] ?? ($details['name'] ?? strtoupper($locale)),
        ])->values();
    }

    public function publicLocales(): array
    {
        if (!Schema::hasTable('translation_locales')) {
            return config('localization.public_locales', ['en']);
        }

        $locales = TranslationLocale::query()
            ->where('is_enabled', true)
            ->orderByDesc('is_default')
            ->orderBy('locale')
            ->pluck('locale')
            ->all();

        return $locales ?: config('localization.public_locales', ['en']);
    }

    public function switcherEnabled(): bool
    {
        return count($this->publicLocales()) > 1;
    }

    public function locale(string $locale): ?TranslationLocale
    {
        if (!Schema::hasTable('translation_locales')) {
            return null;
        }

        return TranslationLocale::query()->find($locale);
    }

    public function setEnabled(string $locale, bool $enabled, ?int $adminId): TranslationLocale
    {
        $definition = config("localization.editor_locales.{$locale}");
        abort_unless(is_array($definition) && $locale !== 'en', 404);

        $record = TranslationLocale::query()->firstOrNew(['locale' => $locale]);
        $record->fill([
            'name' => $definition['name'] ?? strtoupper($locale),
            'native_name' => $definition['native_name'] ?? ($definition['name'] ?? strtoupper($locale)),
            'is_default' => false,
            'is_enabled' => $enabled,
            'enabled_at' => $enabled ? now() : null,
            'updated_by' => $adminId,
        ])->save();

        return $record;
    }
}
