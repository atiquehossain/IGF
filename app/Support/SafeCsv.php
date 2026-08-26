<?php

namespace App\Support;

use BackedEnum;
use DateTimeInterface;
use JsonSerializable;

final class SafeCsv
{
    public const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * Convert an arbitrary scalar into a spreadsheet-safe UTF-8 CSV cell.
     *
     * Formula neutralization is applied after leading whitespace and control
     * bytes are inspected so values such as "  =cmd" cannot bypass it. The
     * original visible whitespace remains intact after the apostrophe prefix.
     */
    public static function cell(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        } elseif ($value instanceof DateTimeInterface) {
            $value = $value->format(DateTimeInterface::ATOM);
        } elseif ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        $cell = str_replace("\0", '', (string) ($value ?? ''));
        $probe = preg_replace('/\A[\p{Z}\s\x00-\x1F\x7F]*/u', '', $cell);
        if (is_string($probe) && preg_match('/\A[=+\-@]/u', $probe) === 1) {
            return "'" . $cell;
        }

        return $cell;
    }

    /** @param resource $stream
     *  @param iterable<mixed> $row
     */
    public static function writeRow($stream, iterable $row): void
    {
        $cells = [];
        foreach ($row as $value) {
            $cells[] = self::cell($value);
        }

        if (fputcsv($stream, $cells, ',', '"', '') === false) {
            throw new \RuntimeException('Unable to write the CSV response.');
        }
    }
}
