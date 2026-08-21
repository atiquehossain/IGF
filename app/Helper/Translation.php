<?php

namespace App\Helper;

use App\Models\TranslationString;
use Illuminate\Support\Facades\Schema;

class Translation
{

    public static function languageList()
    {
        $arr = (array) [
            (object) ['id' => 'en', 'name' => 'English', 'assets' => 'image/flags/en.png']
        ];

        return config('app.localization') ? $arr : [$arr[0]];
    }

    private static function translations($json)
    {
        if (!file_exists($json)) {
            return [];
        }
        return json_decode(file_get_contents($json), true);
    }

    public static function language($locale = null)
    {
        $locale = empty($locale) ? app()->getLocale() : @$locale;
        $english = Translation::translations(resource_path('lang/en.json'));
        $localized = $locale === 'en'
            ? $english
            : Translation::translations(resource_path('lang/' . $locale . '.json'));
        $translations = array_replace_recursive($english, $localized);

        if ($locale !== 'en' && Schema::hasTable('translation_strings')) {
            TranslationString::query()
                ->where('locale', $locale)
                ->where('status', 'translated')
                ->whereNotNull('value')
                ->get(['key', 'value'])
                ->each(function (TranslationString $translation) use (&$translations): void {
                    data_set($translations, $translation->key, $translation->value);
                });
        }

        return $translations;
    }

}
