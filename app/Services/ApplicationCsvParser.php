<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

final class ApplicationCsvParser
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_ROWS = 20_000;
    public const MAX_COLUMNS = 100;
    public const MAX_CELL_BYTES = 20_000;

    /**
     * @return array{headers: list<string>, rows: list<array{row_number:int, values:list<string>}>, checksum:string}
     */
    public function inspect(UploadedFile|string $source): array
    {
        $path = $source instanceof UploadedFile ? $source->getRealPath() : $source;
        if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The CSV file is not readable.');
        }

        $bytes = filesize($path);
        if (!is_int($bytes) || $bytes < 1 || $bytes > self::MAX_BYTES) {
            throw new RuntimeException('The CSV file must be between 1 byte and 10 MiB.');
        }

        $stream = fopen($path, 'rb');
        if (!is_resource($stream)) {
            throw new RuntimeException('The CSV file could not be opened.');
        }

        try {
            $headers = $this->readRow($stream, 1);
            if ($headers === null) {
                throw new RuntimeException('The CSV file does not contain a header row.');
            }
            if (isset($headers[0])) {
                $headers[0] = preg_replace('/\A\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
            }
            $headers = array_map(fn (string $header): string => trim($header), $headers);
            $this->assertHeaders($headers);

            $rows = [];
            $rowNumber = 1;
            while (($row = $this->readRow($stream, ++$rowNumber)) !== null) {
                if (count($rows) >= self::MAX_ROWS) {
                    throw new RuntimeException('The CSV file contains more than 20,000 data rows.');
                }
                if ($this->blank($row)) {
                    continue;
                }
                if (count($row) !== count($headers)) {
                    throw new RuntimeException("CSV row {$rowNumber} has " . count($row) . ' columns; expected ' . count($headers) . '.');
                }
                $rows[] = ['row_number' => $rowNumber, 'values' => $row];
            }

            return [
                'headers' => array_values($headers),
                'rows' => $rows,
                'checksum' => hash_file('sha256', $path),
            ];
        } finally {
            fclose($stream);
        }
    }

    /** @param resource $stream
     *  @return list<string>|null
     */
    private function readRow($stream, int $rowNumber): ?array
    {
        $row = fgetcsv($stream, self::MAX_CELL_BYTES * self::MAX_COLUMNS, ',', '"', '');
        if ($row === false) {
            return null;
        }
        if (count($row) > self::MAX_COLUMNS) {
            throw new RuntimeException("CSV row {$rowNumber} contains more than 100 columns.");
        }

        return array_map(function (mixed $cell) use ($rowNumber): string {
            $cell = (string) ($cell ?? '');
            if (strlen($cell) > self::MAX_CELL_BYTES) {
                throw new RuntimeException("CSV row {$rowNumber} contains a cell larger than 20,000 bytes.");
            }
            if (str_contains($cell, "\0") || !mb_check_encoding($cell, 'UTF-8')) {
                throw new RuntimeException("CSV row {$rowNumber} contains invalid UTF-8 or a NUL byte.");
            }

            return $cell;
        }, $row);
    }

    /** @param list<string> $headers */
    private function assertHeaders(array $headers): void
    {
        if ($headers === [] || count($headers) > self::MAX_COLUMNS) {
            throw new RuntimeException('The CSV header must contain between 1 and 100 columns.');
        }
        if (array_filter($headers, fn (string $header): bool => $header === '') !== []) {
            throw new RuntimeException('Every CSV column requires a non-empty header.');
        }

        $normalized = array_map(fn (string $header): string => mb_strtolower($header), $headers);
        if (count(array_unique($normalized)) !== count($normalized)) {
            throw new RuntimeException('CSV column headers must be unique.');
        }
    }

    /** @param list<string> $row */
    private function blank(array $row): bool
    {
        return array_filter($row, fn (string $cell): bool => trim($cell) !== '') === [];
    }
}
