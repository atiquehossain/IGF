<?php

namespace App\Support;

final class AdminUi
{
    private const ICON_TOKEN_PATTERN = '/\A(?:fa|fa[brsld]|fa-[a-z0-9]+(?:-[a-z0-9]+)*)\z/i';

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
}
