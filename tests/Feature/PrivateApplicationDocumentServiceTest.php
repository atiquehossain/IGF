<?php

namespace Tests\Feature;

use App\Services\PrivateApplicationDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class PrivateApplicationDocumentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(PrivateApplicationDocumentService::DISK);
    }

    public function test_it_stages_a_verified_randomized_private_pdf_and_downloads_it_safely(): void
    {
        $service = app(PrivateApplicationDocumentService::class);
        $document = $service->stagePdf($this->pdf('Candidate CV (final).pdf'));

        $this->assertMatchesRegularExpression('#\Adocuments/[a-f0-9]{48}\.pdf\z#', $document->path);
        $this->assertSame('Candidate CV (final).pdf', $document->originalName);
        $this->assertSame('application/pdf', $document->mimeType);
        $this->assertSame(64, strlen($document->sha256));
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertExists($document->path);
        Storage::disk('public')->assertMissing($document->path);

        $response = $service->download(
            $document->disk,
            $document->path,
            $document->bytes,
            $document->sha256,
            "Candidate\r\nInjected.pdf",
        );
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString("\r", (string) $response->headers->get('Content-Disposition'));
    }

    public function test_it_rejects_wrong_extensions_false_pdfs_incomplete_and_active_content(): void
    {
        $service = app(PrivateApplicationDocumentService::class);

        foreach ([
            UploadedFile::fake()->createWithContent('cv.php', $this->pdfBytes()),
            UploadedFile::fake()->createWithContent('cv.pdf', 'plain text pretending to be a PDF'),
            UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.7\nno trailer"),
            UploadedFile::fake()->createWithContent('cv.pdf', $this->pdfBytes(extraObjects: [
                5 => '<< /S /JavaScript /JS (alert) >>',
            ])),
        ] as $file) {
            try {
                $service->stagePdf($file);
                $this->fail('Unsafe document was accepted.');
            } catch (RuntimeException) {
                $this->assertSame([], Storage::disk(PrivateApplicationDocumentService::DISK)->allFiles());
            }
        }
    }

    public function test_it_enforces_the_exact_five_mebibyte_boundary_and_contains_deletes(): void
    {
        $service = app(PrivateApplicationDocumentService::class);
        $contents = $this->pdfAtExactSize(PrivateApplicationDocumentService::MAX_BYTES);
        $document = $service->stagePdf(UploadedFile::fake()->createWithContent('boundary.pdf', $contents));
        $this->assertSame(PrivateApplicationDocumentService::MAX_BYTES, $document->bytes);

        $service->discard($document);
        Storage::disk(PrivateApplicationDocumentService::DISK)->assertMissing($document->path);

        $this->expectException(RuntimeException::class);
        $service->deleteStored(PrivateApplicationDocumentService::DISK, '../outside.pdf');
    }

    public function test_it_rejects_malformed_cross_references_polyglots_embedded_files_and_obfuscated_actions(): void
    {
        $service = app(PrivateApplicationDocumentService::class);
        $valid = $this->pdfBytes();

        $unsafe = [
            preg_replace('/startxref\n[0-9]+/', "startxref\n1", $valid),
            str_replace('<< /Size 5 /Root 1 0 R >>', '<< /Size 5             >>', $valid),
            $valid . "PK\x03\x04appended-archive",
            $valid . "\r\nMZappended-executable",
            $this->pdfBytes(extraObjects: [
                5 => "<< /Type /EmbeddedFile /Length 0 >>\nstream\n\nendstream",
            ]),
            $this->pdfBytes(extraObjects: [
                5 => '<< /S /#4AavaScript /#4AS (alert) >>',
            ]),
        ];

        foreach ($unsafe as $index => $contents) {
            $this->assertIsString($contents);
            try {
                $service->stagePdf(UploadedFile::fake()->createWithContent("unsafe-{$index}.pdf", $contents));
                $this->fail("Unsafe PDF fixture {$index} was accepted.");
            } catch (RuntimeException) {
                $this->assertSame([], Storage::disk(PrivateApplicationDocumentService::DISK)->allFiles());
            }
        }
    }

    public function test_download_fails_closed_when_stored_bytes_or_document_metadata_are_tampered(): void
    {
        $service = app(PrivateApplicationDocumentService::class);
        $document = $service->stagePdf($this->pdf('candidate.pdf'));

        Storage::disk(PrivateApplicationDocumentService::DISK)->put(
            $document->path,
            $this->pdfBytes('tampered'),
        );

        try {
            $service->download(
                $document->disk,
                $document->path,
                $document->bytes,
                $document->sha256,
                $document->originalName,
            );
            $this->fail('A tampered stored document was downloadable.');
        } catch (NotFoundHttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        Storage::disk(PrivateApplicationDocumentService::DISK)->put($document->path, $this->pdfBytes());
        $this->expectException(NotFoundHttpException::class);
        $service->download(
            $document->disk,
            $document->path,
            $document->bytes,
            str_repeat('0', 64),
            $document->originalName,
        );
    }

    public function test_isolated_parser_timeout_rejects_without_storing_the_upload(): void
    {
        config()->set('application_documents.parser_timeout_seconds', 1);
        config()->set('application_documents.parser_worker_path', base_path('tests/Fixtures/slow_application_pdf_worker.php'));

        try {
            app(PrivateApplicationDocumentService::class)->stagePdf($this->pdf('timeout.pdf'));
            $this->fail('A parser process that exceeds its deadline must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('could not be inspected safely', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk(PrivateApplicationDocumentService::DISK)->allFiles());
    }

    public function test_isolated_parser_failure_is_generic_and_rejects_without_storing_the_upload(): void
    {
        config()->set('application_documents.parser_worker_path', base_path('tests/Fixtures/failing_application_pdf_worker.php'));

        try {
            app(PrivateApplicationDocumentService::class)->stagePdf($this->pdf('failure.pdf'));
            $this->fail('A failed parser process must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The PDF document is malformed, unsafe, or unsupported.', $exception->getMessage());
            $this->assertStringNotContainsString('parser-internal-sensitive-diagnostic', $exception->getMessage());
        }

        $this->assertSame([], Storage::disk(PrivateApplicationDocumentService::DISK)->allFiles());
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pdfBytes());
    }

    /** @param array<int, string> $extraObjects */
    private function pdfBytes(string $pageStream = '', array $extraObjects = []): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>',
            4 => "<< /Length " . strlen($pageStream) . " >>\nstream\n{$pageStream}\nendstream",
        ] + $extraObjects;
        ksort($objects);

        $pdf = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($number = 1; $number < $size; $number++) {
            $pdf .= isset($offsets[$number])
                ? sprintf("%010d 00000 n \n", $offsets[$number])
                : "0000000000 00000 f \n";
        }
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    private function pdfAtExactSize(int $targetBytes): string
    {
        $padding = $targetBytes - strlen($this->pdfBytes());
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $pdf = $this->pdfBytes(str_repeat(' ', max(0, $padding)));
            $difference = $targetBytes - strlen($pdf);
            if ($difference === 0) {
                return $pdf;
            }
            $padding += $difference;
        }

        $this->fail('Could not construct the exact-size PDF fixture.');
    }
}
