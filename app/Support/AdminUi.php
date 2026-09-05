<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;
use Stringable;

final class AdminUi
{
    private const ICON_TOKEN_PATTERN = '/\A(?:fa|fa[brsld]|fa-[a-z0-9]+(?:-[a-z0-9]+)*)\z/i';

    private const TRANSLATION_KEY_PATTERN = '/\A[a-z0-9_-]+(?:\.[a-z0-9_-]+)*\z/i';

    /**
     * Return trusted admin-interface copy in the current language.
     *
     * Admin chrome deliberately has its own catalogue so public, database-backed
     * content cannot replace labels or instructions used to operate the CMS.
     */
    public static function text(string $key, array $replace = [], ?string $locale = null): string
    {
        $key = trim($key);
        if ($key === '' || preg_match(self::TRANSLATION_KEY_PATTERN, $key) !== 1) {
            return '';
        }

        $locale = self::supportedLocale($locale);
        $lookup = "admin_ui.{$key}";
        $english = Lang::get($lookup, [], 'en');
        $translated = Lang::get($lookup, [], $locale);

        $fallback = is_string($english) && $english !== $lookup
            ? $english
            : self::humanizeKey($key);
        $value = is_string($translated) && $translated !== $lookup
            ? $translated
            : $fallback;

        foreach ($replace as $name => $replacement) {
            if (!is_scalar($replacement) && !$replacement instanceof Stringable) {
                continue;
            }

            $plainReplacement = self::plainText((string) $replacement);
            $value = str_replace([':'.$name, '{'.$name.'}'], $plainReplacement, $value);
        }

        return self::plainText($value);
    }

    /**
     * Return a recursively merged, plain-text-only dictionary for JavaScript UIs.
     */
    public static function section(string $key, ?string $locale = null): array
    {
        $key = trim($key);
        if ($key === '' || preg_match(self::TRANSLATION_KEY_PATTERN, $key) !== 1) {
            return [];
        }

        $lookup = "admin_ui.{$key}";
        $english = Lang::get($lookup, [], 'en');
        $translated = Lang::get($lookup, [], self::supportedLocale($locale));
        $english = is_array($english) ? $english : [];
        $translated = is_array($translated) ? $translated : [];

        return self::plainDictionary(array_replace_recursive($english, $translated));
    }

    public static function iconValidationRules(): array
    {
        return [
            'nullable',
            'string',
            'max:160',
            static function (string $attribute, mixed $value, $fail): void {
                if (!self::isValidIconClass($value)) {
                    $fail("The {$attribute} field must contain only Font Awesome class names.");
                }
            },
        ];
    }

    public static function isValidIconClass(mixed $value): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return true;
        }

        $tokens = preg_split('/\s+/', trim((string) $value)) ?: [];
        if ($tokens === [] || count($tokens) > 6) {
            return false;
        }

        foreach ($tokens as $token) {
            if (preg_match(self::ICON_TOKEN_PATTERN, $token) !== 1) {
                return false;
            }
        }

        return true;
    }

    public static function iconClass(mixed $value, string $fallback = 'fa-cogs'): string
    {
        if (!self::isValidIconClass($value)) {
            return $fallback;
        }

        $tokens = preg_split('/\s+/', trim((string) $value)) ?: [];
        $tokens = array_values(array_unique(array_filter($tokens, 'strlen')));

        return $tokens === [] ? $fallback : implode(' ', $tokens);
    }

    public static function label(mixed $value): string
    {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function supportedLocale(?string $locale): string
    {
        $locale = strtolower(trim($locale ?: app()->getLocale()));
        $configured = [];
        foreach ((array) config('localization.editor_locales', ['en', 'bn']) as $key => $value) {
            if (is_string($key) && !is_numeric($key)) {
                $configured[] = strtolower($key);
            } elseif (is_scalar($value)) {
                $configured[] = strtolower((string) $value);
            }
        }
        $configured[] = 'en';

        return in_array($locale, array_unique($configured), true) ? $locale : 'en';
    }

    private static function plainDictionary(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::plainDictionary($value);
                continue;
            }

            $values[$key] = self::plainText(is_scalar($value) || $value instanceof Stringable ? (string) $value : '');
        }

        return $values;
    }

    private static function plainText(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return trim($value);
    }

    private static function humanizeKey(string $key): string
    {
        $leaf = str_replace(['-', '_'], ' ', (string) strrchr('.'.$key, '.'));

        return ucfirst(trim($leaf, '. '));
    }
}
