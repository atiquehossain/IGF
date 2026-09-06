<?php

namespace App\Support;

use Throwable;

final class BangladeshLocationLabels
{
    /** @var array{divisions: array<string,string>, districts: array<string,string>, upazilas: array<string,string>}|null */
    private static ?array $banglaLabels = null;

    public static function division(string $name, string $locale): string
    {
        return self::lookup('divisions', [$name], $locale) ?? $name;
    }

    public static function district(string $division, string $name, string $locale): string
    {
        return self::lookup('districts', [$division, $name], $locale) ?? $name;
    }

    public static function upazila(string $division, string $district, string $name, string $locale): string
    {
        return self::lookup('upazilas', [$division, $district, $name], $locale) ?? $name;
    }

    private static function lookup(string $level, array $path, string $locale): ?string
    {
        if (!str_starts_with(strtolower($locale), 'bn')) {
            return null;
        }

        $labels = self::$banglaLabels ??= self::loadBanglaLabels();

        return $labels[$level][self::key($path)] ?? null;
    }

    /**
     * @return array{divisions: array<string,string>, districts: array<string,string>, upazilas: array<string,string>}
     */
    private static function loadBanglaLabels(): array
    {
        $labels = ['divisions' => [], 'districts' => [], 'upazilas' => []];

        try {
            $path = database_path('data/bangladesh-administrative-divisions.bn.json');
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            foreach ($decoded['divisions'] ?? [] as $division) {
                $divisionName = trim((string) ($division['name'] ?? ''));
                $divisionLabel = trim((string) ($division['label'] ?? ''));
                if ($divisionName === '' || $divisionLabel === '') {
                    continue;
                }
                $labels['divisions'][self::key([$divisionName])] = $divisionLabel;

                foreach ($division['districts'] ?? [] as $district) {
                    $districtName = trim((string) ($district['name'] ?? ''));
                    $districtLabel = trim((string) ($district['label'] ?? ''));
                    if ($districtName === '' || $districtLabel === '') {
                        continue;
                    }
                    $labels['districts'][self::key([$divisionName, $districtName])] = $districtLabel;

                    foreach ($district['upazilas'] ?? [] as $upazila) {
                        $upazilaName = trim((string) ($upazila['name'] ?? ''));
                        $upazilaLabel = trim((string) ($upazila['label'] ?? ''));
                        if ($upazilaName !== '' && $upazilaLabel !== '') {
                            $labels['upazilas'][self::key([$divisionName, $districtName, $upazilaName])] = $upazilaLabel;
                        }
                    }
                }
            }
        } catch (Throwable) {
            // Canonical English names remain usable if this optional locale data is unavailable.
        }

        return $labels;
    }

    private static function key(array $parts): string
    {
        return implode("\0", array_map(
            fn (mixed $part): string => mb_strtolower(trim((string) $part)),
            $parts
        ));
    }
}
