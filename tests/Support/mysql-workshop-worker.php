<?php

use App\Models\Admin;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Services\WorkshopRegistrationService;
use App\Services\WorkshopRegistrationWorkflowService;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\MySqlConcurrencyConnection;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
MySqlConcurrencyConnection::configure();

try {
    $action = (string) ($argv[1] ?? '');
    $recordId = filter_var($argv[2] ?? null, FILTER_VALIDATE_INT);
    $encodedPayload = (string) ($argv[3] ?? '');
    $startAt = (float) ($argv[4] ?? 0);
    if ($recordId === false || $recordId < 1) {
        throw new RuntimeException('A valid worker record id is required.');
    }
    while (microtime(true) < $startAt) {
        usleep(1000);
    }

    if ($action === 'submit') {
        $decoded = base64_decode($encodedPayload, true);
        $payload = is_string($decoded) ? json_decode($decoded, true, flags: JSON_THROW_ON_ERROR) : null;
        if (!is_array($payload)) {
            throw new RuntimeException('A valid submission payload is required.');
        }
        $result = $app->make(WorkshopRegistrationService::class)->submit(
            Workshop::query()->findOrFail($recordId),
            $payload,
        );
    } elseif ($action === 'cancel') {
        $actorId = filter_var($encodedPayload, FILTER_VALIDATE_INT);
        if ($actorId === false || $actorId < 1) {
            throw new RuntimeException('A valid actor id is required.');
        }
        $result = $app->make(WorkshopRegistrationWorkflowService::class)->transition(
            WorkshopRegistration::query()->findOrFail($recordId),
            WorkshopRegistration::STATUS_CANCELLED,
            Admin::query()->findOrFail($actorId),
        );
    } else {
        throw new RuntimeException('Unsupported concurrency worker action.');
    }

    fwrite(STDOUT, json_encode([
        'id' => (int) $result->getKey(),
        'status' => $result->workflow_status,
    ], JSON_THROW_ON_ERROR));
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage());
    exit(1);
}
