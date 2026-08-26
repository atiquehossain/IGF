<?php

namespace App\Services;

use App\Contracts\PrivateFileDeletion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

final class PrivateApplicationDocumentService implements PrivateFileDeletion
{
    public const DISK = 'applicant_documents';
    public const MAX_BYTES = 5 * 1024 * 1024;

    public function stagePdf(UploadedFile $file): StagedPrivateDocument
    {
        if (!$file->isValid()) {
            throw new RuntimeException('The uploaded PDF is not valid.');
        }
        if (strtolower((string) $file->getClientOriginalExtension()) !== 'pdf') {
            throw new RuntimeException('Applicant documents must use the .pdf extension.');
        }

        $temporaryPath = $file->getRealPath();
        if (!is_string($temporaryPath) || $temporaryPath === '' || !is_file($temporaryPath)) {
            throw new RuntimeException('The uploaded PDF is not readable.');
        }
        $bytes = filesize($temporaryPath);
        if (!is_int($bytes) || $bytes < 8 || $bytes > self::MAX_BYTES) {
            throw new RuntimeException('Applicant documents must be valid PDFs no larger than 5 MiB.');
        }

        $contents = file_get_contents($temporaryPath);
        if (!is_string($contents) || strlen($contents) !== $bytes) {
            throw new RuntimeException('The uploaded PDF could not be read completely.');
        }
        $this->assertSafePdf($contents);

        $uuid = (string) Str::uuid();
        $path = 'documents/' . bin2hex(random_bytes(24)) . '.pdf';
        $disk = Storage::disk(self::DISK);

        try {
            if (!$disk->put($path, $contents, ['visibility' => 'private'])) {
                throw new RuntimeException('The applicant document could not be stored.');
            }
            $stored = $disk->get($path);
            if (!hash_equals(hash('sha256', $contents), hash('sha256', $stored))) {
                throw new RuntimeException('The stored applicant document failed integrity verification.');
            }
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }

        return new StagedPrivateDocument(
            $uuid,
            self::DISK,
            $path,
            $this->safeDownloadName($file->getClientOriginalName()),
            'application/pdf',
            $bytes,
            hash('sha256', $contents),
        );
    }

    public function discard(?StagedPrivateDocument $document): void
    {
        if ($document && $document->disk === self::DISK && $this->safePath($document->path)) {
            Storage::disk(self::DISK)->delete($document->path);
        }
    }

    public function deleteStored(string $disk, string $path): void
    {
        if ($disk !== self::DISK || !$this->safePath($path)) {
            throw new RuntimeException('The applicant document path is invalid.');
        }

        $storage = Storage::disk(self::DISK);
        if ($storage->exists($path) && !$storage->delete($path)) {
            throw new RuntimeException('The applicant document could not be securely deleted.');
        }
    }

    public function download(
        string $disk,
        string $path,
        int $expectedBytes,
        string $expectedSha256,
        ?string $originalName = null,
    ): BinaryFileResponse
    {
        $storage = Storage::disk(self::DISK);
        if ($disk !== self::DISK
            || !$this->safePath($path)
            || $expectedBytes < 8
            || $expectedBytes > self::MAX_BYTES
            || preg_match('/\A[a-f0-9]{64}\z/D', $expectedSha256) !== 1
            || !$storage->exists($path)) {
            abort(404);
        }

        $stored = $storage->get($path);
        if (strlen($stored) !== $expectedBytes
            || !hash_equals($expectedSha256, hash('sha256', $stored))) {
            abort(404);
        }

        try {
            $this->assertSafePdf($stored);
        } catch (Throwable) {
            abort(404);
        }

        $response = response()->download(
            $storage->path($path),
            $this->safeDownloadName($originalName ?: 'applicant-document.pdf'),
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
                'X-Download-Options' => 'noopen',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ]
        );
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    private function assertSafePdf(string $contents): void
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        if ($mime !== 'application/pdf' || preg_match('/\A%PDF-1\.[0-9](?:\r\n|\r|\n)/D', $contents) !== 1) {
            throw new RuntimeException('The file contents are not a complete PDF document.');
        }

        if (preg_match('/startxref[\x00\x09\x0A\x0C\x0D\x20]+([0-9]+)[\x00\x09\x0A\x0C\x0D\x20]+%%EOF[\x00\x09\x0A\x0C\x0D\x20]*\z/D', $contents, $matches) !== 1) {
            throw new RuntimeException('The file contents are not a complete PDF document.');
        }

        $xrefOffset = (int) $matches[1];
        $xrefPrefix = substr($contents, $xrefOffset, 80);
        if ($xrefOffset <= 0
            || $xrefOffset >= strlen($contents)
            || (preg_match('/\Axref\b/', $xrefPrefix) !== 1
                && preg_match('/\A[0-9]+[\x00\x09\x0A\x0C\x0D\x20]+[0-9]+[\x00\x09\x0A\x0C\x0D\x20]+obj\b/', $xrefPrefix) !== 1)) {
            throw new RuntimeException('The PDF cross-reference table is invalid.');
        }

        $worker = (string) config('application_documents.parser_worker_path');
        $timeout = max(1.0, min(10.0, (float) config('application_documents.parser_timeout_seconds', 5)));
        if ($worker === '' || !is_file($worker) || PHP_BINARY === '' || !is_file(PHP_BINARY)) {
            throw new RuntimeException('The isolated PDF inspector is unavailable.');
        }

        $process = new Process(
            [PHP_BINARY, '-d', 'memory_limit=128M', '-d', 'display_errors=0', '-d', 'log_errors=0', $worker],
            base_path(),
            ['IGF_APPLICATION_PDF_WORKER' => '1'],
            $contents,
            $timeout,
        );
        $process->setIdleTimeout($timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('The PDF document could not be inspected safely.', previous: $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException('The isolated PDF inspector failed.', previous: $exception);
        }

        if (!$process->isSuccessful()) {
            throw new RuntimeException('The PDF document is malformed, unsafe, or unsupported.');
        }
    }

    private function safePath(string $path): bool
    {
        return preg_match('#\Adocuments/[a-f0-9]{48}\.pdf\z#D', $path) === 1;
    }

    private function safeDownloadName(string $name): string
    {
        $base = pathinfo(str_replace(["\r", "\n", "\0", '/', '\\'], '-', $name), PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[^\pL\pN ._()-]+/u', '-', $base), ' .-_');
        $base = mb_substr($base === '' ? 'applicant-document' : $base, 0, 100);

        return $base . '.pdf';
    }
}
