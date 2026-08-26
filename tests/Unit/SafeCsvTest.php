<?php

namespace Tests\Unit;

use App\Support\SafeCsv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SafeCsvTest extends TestCase
{
    #[DataProvider('dangerousCells')]
    public function test_it_neutralizes_formula_cells_even_after_whitespace_or_controls(string $input): void
    {
        $this->assertSame("'" . $input, SafeCsv::cell($input));
    }

    public static function dangerousCells(): array
    {
        return [
            'equals' => ['=2+2'],
            'plus' => ['+SUM(A1:A2)'],
            'minus' => ['-1+2'],
            'at' => ['@IMPORTXML("https://example.test")'],
            'spaces' => ['   =cmd'],
            'tab' => ["\t+cmd"],
            'line break' => ["\r\n-cmd"],
            'unicode space' => ["\u{00A0}@cmd"],
        ];
    }

    public function test_it_preserves_normal_utf8_and_serializes_structured_values(): void
    {
        $this->assertSame('বাংলা workshop', SafeCsv::cell('বাংলা workshop'));
        $this->assertSame('{"answer":"Yes"}', SafeCsv::cell(['answer' => 'Yes']));
        $this->assertSame('', SafeCsv::cell(null));
    }

    public function test_it_writes_real_rfc_compatible_csv_without_formula_execution(): void
    {
        $stream = fopen('php://temp', 'w+b');
        $this->assertIsResource($stream);

        SafeCsv::writeRow($stream, ['Name', 'Answer']);
        SafeCsv::writeRow($stream, ['A "quoted" name', " =HYPERLINK(\"https://example.test\")\nnext"]);
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        $this->assertSame(
            "Name,Answer\n\"A \"\"quoted\"\" name\",\"' =HYPERLINK(\"\"https://example.test\"\")\nnext\"\n",
            str_replace("\r\n", "\n", (string) $contents)
        );
    }
}
