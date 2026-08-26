<?php

use App\Services\ApplicationPdfStructureInspector;
use App\Services\PrivateApplicationDocumentService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli' || getenv('IGF_APPLICATION_PDF_WORKER') !== '1') {
    exit(2);
}

$contents = stream_get_contents(STDIN, PrivateApplicationDocumentService::MAX_BYTES + 1);
if (!is_string($contents) || strlen($contents) < 8 || strlen($contents) > PrivateApplicationDocumentService::MAX_BYTES) {
    exit(2);
}

try {
    (new ApplicationPdfStructureInspector())->inspect($contents);
    exit(0);
} catch (Throwable) {
    exit(1);
}
