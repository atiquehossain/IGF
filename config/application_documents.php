<?php

return [
    // Parsing untrusted PDFs is isolated in a memory-limited child process.
    // Keep this small enough to protect web capacity and large enough for an
    // ordinary 100-page, 5 MiB applicant document.
    'parser_timeout_seconds' => env('APPLICATION_PDF_PARSER_TIMEOUT_SECONDS', 5),
    'parser_worker_path' => base_path('app/Support/inspect_application_pdf.php'),
];
