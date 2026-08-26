<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Document as ParsedPdfDocument;
use Smalot\PdfParser\Element;
use Smalot\PdfParser\Element\ElementArray;
use Smalot\PdfParser\Header;
use Smalot\PdfParser\Parser as PdfParser;
use Smalot\PdfParser\PDFObject;
use Throwable;

final class ApplicationPdfStructureInspector
{
    private const MAX_PAGES = 100;
    private const MAX_PARSED_OBJECTS = 10000;
    private const MAX_DECODED_STREAM_BYTES = 20 * 1024 * 1024;

    /** @var list<string> */
    private const FORBIDDEN_PDF_NAMES = [
        'AA', 'AcroForm', 'Collection', 'EmbeddedFile', 'EmbeddedFiles', 'Encrypt', 'EF',
        'GoToE', 'JavaScript', 'JS', 'Launch', 'Movie', 'OpenAction', 'RichMedia', 'Rendition',
        'Screen', 'Sound', 'SubmitForm', 'ThreeD', 'XFA',
    ];

    public function inspect(string $contents): void
    {
        try {
            $config = new PdfParserConfig();
            $config->setRetainImageContent(false);
            $config->setDecodeMemoryLimit(self::MAX_DECODED_STREAM_BYTES);
            $document = (new PdfParser([], $config))->parseContent($contents);
            $this->assertParsedPdfIsSafe($document);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException('The PDF document is malformed or unsupported.', previous: $exception);
        }
    }

    private function assertParsedPdfIsSafe(ParsedPdfDocument $document): void
    {
        $objects = $document->getObjects();
        if ($objects === [] || count($objects) > self::MAX_PARSED_OBJECTS) {
            throw new RuntimeException('The PDF document structure is invalid or too complex.');
        }

        $trailer = $document->getTrailer();
        if (!$trailer->has('Root')) {
            throw new RuntimeException('The PDF document has no valid catalog.');
        }

        $pages = $document->getPages();
        if ($pages === [] || count($pages) > self::MAX_PAGES) {
            throw new RuntimeException('Applicant PDFs must contain between 1 and 100 pages.');
        }

        $this->assertHeaderIsSafe($trailer);
        foreach ($objects as $object) {
            $header = $object->getHeader();
            if ($header instanceof Header) {
                $this->assertHeaderIsSafe($header);
            }
        }
    }

    private function assertHeaderIsSafe(Header $header, int $depth = 0): void
    {
        if ($depth > 20) {
            throw new RuntimeException('The PDF document structure is too deeply nested.');
        }

        foreach ($header->getElements() as $key => $element) {
            $name = $this->decodedPdfName((string) $key);
            if (in_array($name, self::FORBIDDEN_PDF_NAMES, true)) {
                throw new RuntimeException('Active, encrypted, form, or embedded PDF content is not accepted.');
            }

            if ($element instanceof PDFObject) {
                continue;
            }
            if ($element instanceof Header) {
                $this->assertHeaderIsSafe($element, $depth + 1);
                continue;
            }
            if ($element instanceof ElementArray) {
                $this->assertPdfArrayIsSafe($element, $depth + 1);
                continue;
            }
            if ($element instanceof Element && in_array($name, ['S', 'Subtype', 'Type'], true)) {
                $value = $this->decodedPdfName(ltrim((string) $element->getContent(), '/'));
                if (in_array($value, self::FORBIDDEN_PDF_NAMES, true)) {
                    throw new RuntimeException('Active, encrypted, form, or embedded PDF content is not accepted.');
                }
            }
        }
    }

    private function assertPdfArrayIsSafe(ElementArray $array, int $depth): void
    {
        if ($depth > 20) {
            throw new RuntimeException('The PDF document structure is too deeply nested.');
        }

        foreach ($array->getRawContent() as $element) {
            if ($element instanceof Header) {
                $this->assertHeaderIsSafe($element, $depth + 1);
            } elseif ($element instanceof ElementArray) {
                $this->assertPdfArrayIsSafe($element, $depth + 1);
            }
        }
    }

    private function decodedPdfName(string $name): string
    {
        return (string) preg_replace_callback(
            '/#([0-9a-f]{2})/i',
            static fn (array $matches): string => chr(hexdec($matches[1])),
            $name,
        );
    }
}
