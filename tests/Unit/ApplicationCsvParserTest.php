<?php

namespace Tests\Unit;

use App\Services\ApplicationCsvParser;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ApplicationCsvParserTest extends TestCase
{
    public function test_it_reads_bom_utf8_google_style_csv_as_inert_text(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'responses.csv',
            "\xEF\xBB\xBFTimestamp,Email,Answer\n2026-08-26 10:00,a@example.test,=2+2\n\n"
        );

        $inspection = (new ApplicationCsvParser())->inspect($file);

        $this->assertSame(['Timestamp', 'Email', 'Answer'], $inspection['headers']);
        $this->assertCount(1, $inspection['rows']);
        $this->assertSame('=2+2', $inspection['rows'][0]['values'][2], 'Formula-looking input remains inert text and is never evaluated.');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $inspection['checksum']);
    }

    #[DataProvider('invalidCsvFiles')]
    public function test_it_rejects_malformed_or_ambiguous_csv(string $contents, string $message): void
    {
        $file = UploadedFile::fake()->createWithContent('bad.csv', $contents);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        (new ApplicationCsvParser())->inspect($file);
    }

    public static function invalidCsvFiles(): array
    {
        return [
            'blank header' => [",Email\nName,a@example.test\n", 'non-empty header'],
            'duplicate header' => ["Email,email\na@example.test,a@example.test\n", 'unique'],
            'ragged row' => ["Name,Email\nOnly a name\n", 'expected 2'],
            'nul byte' => ["Name\nA\0B\n", 'NUL byte'],
            'invalid utf8' => ["Name\n\xC3\x28\n", 'invalid UTF-8'],
        ];
    }
}
