<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\ApplicationFormVersion;
use App\Models\Workshop;
use App\Models\WorkshopRegistration;
use App\Models\WorkshopRegistrationStatusEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\MySqlConcurrencyConnection;
use Tests\TestCase;

/**
 * Genuine MySQL row-lock test (it intentionally runs outside RefreshDatabase):
 *
 * PowerShell:
 *   $env:IGF_MYSQL_CONCURRENCY_DSN='mysql:host=127.0.0.1;port=3306;dbname=igf_concurrency_test'
 *   $env:IGF_MYSQL_CONCURRENCY_USERNAME='root'
 *   $env:IGF_MYSQL_CONCURRENCY_PASSWORD=''
 *   C:\xampp\php\php.exe artisan test tests/Feature/MySqlWorkshopConcurrencyTest.php
 *
 * The named database must be dedicated to this test because migrate:fresh resets it.
 */
class MySqlWorkshopConcurrencyTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        if (!MySqlConcurrencyConnection::available()) {
            $this->markTestSkipped(
                MySqlConcurrencyConnection::DSN_ENV . ' is not set; provide a dedicated MySQL test DSN to run genuine lock tests.',
            );
        }

        CarbonImmutable::setTestNow();
        \Illuminate\Support\Carbon::setTestNow();
        $this->originalConnection = DB::getDefaultConnection();
        MySqlConcurrencyConnection::configure();
        Artisan::call('migrate:fresh', [
            '--database' => MySqlConcurrencyConnection::NAME,
            '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->originalConnection)) {
            DB::disconnect(MySqlConcurrencyConnection::NAME);
            DB::setDefaultConnection($this->originalConnection);
        }
        parent::tearDown();
    }

    public function test_competing_processes_allocate_final_seats_and_promote_waitlists_without_overbooking(): void
    {
        [$form, $version] = $this->publishedWorkshopForm();
        $singleSeat = $this->workshop($form, $version, 1, Workshop::REGISTRATION_AUTOMATIC);
        $startAt = microtime(true) + 1.0;
        $submissions = [
            $this->worker('submit', $singleSeat->id, $this->encodedPayload('race-one@example.test'), $startAt),
            $this->worker('submit', $singleSeat->id, $this->encodedPayload('race-two@example.test'), $startAt),
        ];
        $this->runTogether($submissions, 1);

        $this->assertSame(
            [WorkshopRegistration::STATUS_CONFIRMED],
            $singleSeat->registrations()->pluck('workflow_status')->all(),
        );
        $this->assertSame(1, $singleSeat->registrations()->where('workflow_status', WorkshopRegistration::STATUS_CONFIRMED)->count());

        $twoSeats = $this->workshop($form, $version, 2);
        $actor = Admin::create([
            'name' => 'Concurrency actor',
            'username' => 'concurrency-actor',
            'email' => 'concurrency-actor@example.test',
            'status' => 1,
        ]);
        $firstConfirmed = $this->registration($twoSeats, $version, 'confirmed-one@example.test', WorkshopRegistration::STATUS_CONFIRMED, now()->subHours(4));
        $secondConfirmed = $this->registration($twoSeats, $version, 'confirmed-two@example.test', WorkshopRegistration::STATUS_CONFIRMED, now()->subHours(3));
        $oldestWaiting = $this->registration($twoSeats, $version, 'waiting-one@example.test', WorkshopRegistration::STATUS_WAITLISTED, now()->subHours(2));
        $newestWaiting = $this->registration($twoSeats, $version, 'waiting-two@example.test', WorkshopRegistration::STATUS_WAITLISTED, now()->subHour());

        $startAt = microtime(true) + 1.0;
        $cancellations = [
            $this->worker('cancel', $firstConfirmed->id, (string) $actor->id, $startAt),
            $this->worker('cancel', $secondConfirmed->id, (string) $actor->id, $startAt),
        ];
        $this->runTogether($cancellations, 2);

        $this->assertSame(WorkshopRegistration::STATUS_CANCELLED, $firstConfirmed->fresh()->workflow_status);
        $this->assertSame(WorkshopRegistration::STATUS_CANCELLED, $secondConfirmed->fresh()->workflow_status);
        $this->assertSame(WorkshopRegistration::STATUS_CONFIRMED, $oldestWaiting->fresh()->workflow_status);
        $this->assertSame(WorkshopRegistration::STATUS_CONFIRMED, $newestWaiting->fresh()->workflow_status);
        $this->assertSame(2, $twoSeats->registrations()->where('workflow_status', WorkshopRegistration::STATUS_CONFIRMED)->count());
        $this->assertSame(0, $twoSeats->registrations()->where('workflow_status', WorkshopRegistration::STATUS_WAITLISTED)->count());
        $this->assertSame(2, WorkshopRegistrationStatusEvent::query()
            ->whereIn('workshop_registration_id', [$oldestWaiting->id, $newestWaiting->id])
            ->where('source', WorkshopRegistrationStatusEvent::SOURCE_SYSTEM)
            ->where('to_status', WorkshopRegistration::STATUS_CONFIRMED)
            ->count());
    }

    /** @param list<Process> $processes */
    private function runTogether(array $processes, int $expectedSuccesses): void
    {
        foreach ($processes as $process) {
            $process->start();
        }
        $successes = 0;
        foreach ($processes as $process) {
            $process->wait();
            if ($process->isSuccessful()) {
                $successes++;
            } else {
                $this->assertStringContainsString(
                    'ValidationException',
                    $process->getErrorOutput(),
                    "Unexpected concurrency worker failure:\n{$process->getErrorOutput()}",
                );
            }
        }
        $this->assertSame($expectedSuccesses, $successes, 'Unexpected number of successful concurrency workers.');
    }

    private function worker(string $action, int $recordId, string $payload, float $startAt): Process
    {
        $process = new Process([
            PHP_BINARY,
            base_path('tests/Support/mysql-workshop-worker.php'),
            $action,
            (string) $recordId,
            $payload,
            sprintf('%.6F', $startAt),
        ], base_path(), MySqlConcurrencyConnection::workerEnvironment());
        $process->setTimeout(60);

        return $process;
    }

    /** @return array{0: ApplicationForm, 1: ApplicationFormVersion} */
    private function publishedWorkshopForm(): array
    {
        $form = ApplicationForm::create(['purpose' => ApplicationForm::PURPOSE_WORKSHOP, 'name' => 'Concurrency workshop form']);
        $version = ApplicationFormVersion::create([
            'application_form_id' => $form->id,
            'version' => 1,
            'state' => ApplicationFormVersion::STATE_DRAFT,
        ]);
        $this->field($version, 'full-name', ApplicationFormField::TYPE_SHORT_TEXT, 1, true, ApplicationFormField::SYSTEM_FULL_NAME);
        $this->field($version, 'email', ApplicationFormField::TYPE_EMAIL, 2, true, ApplicationFormField::SYSTEM_EMAIL);
        $this->field($version, 'phone', ApplicationFormField::TYPE_PHONE, 3, false, ApplicationFormField::SYSTEM_PHONE);
        $this->field($version, 'motivation', ApplicationFormField::TYPE_LONG_TEXT, 4, true);
        $version->update([
            'state' => ApplicationFormVersion::STATE_PUBLISHED,
            'schema_hash' => hash('sha256', 'mysql-concurrency-schema'),
            'published_at' => now(),
        ]);

        return [$form, $version->fresh()];
    }

    private function field(
        ApplicationFormVersion $version,
        string $key,
        string $type,
        int $position,
        bool $required,
        ?string $systemKey = null,
    ): void {
        $field = $version->fields()->create([
            'field_key' => $key,
            'system_key' => $systemKey,
            'type' => $type,
            'position' => $position,
            'is_required' => $required,
        ]);
        $field->translations()->create(['locale' => 'en', 'label' => ucfirst(str_replace('-', ' ', $key))]);
    }

    private function workshop(
        ApplicationForm $form,
        ApplicationFormVersion $version,
        int $capacity,
        string $mode = Workshop::REGISTRATION_WAITLIST,
    ): Workshop
    {
        return Workshop::create([
            'application_form_id' => $form->id,
            'current_form_version_id' => $version->id,
            'publication_status' => Workshop::PUBLICATION_PUBLISHED,
            'visible_from_at' => now()->subDay(),
            'registration_opens_at' => now()->subHour(),
            'registration_closes_at' => now()->addDay(),
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'attendance_mode' => Workshop::ATTENDANCE_OFFLINE,
            'registration_mode' => $mode,
            'capacity' => $capacity,
        ]);
    }

    private function registration(
        Workshop $workshop,
        ApplicationFormVersion $version,
        string $email,
        string $status,
        mixed $statusAt,
    ): WorkshopRegistration {
        return WorkshopRegistration::create([
            'workshop_id' => $workshop->id,
            'application_form_version_id' => $version->id,
            'name' => 'Concurrency registrant',
            'email' => $email,
            'workflow_status' => $status,
            'confirmed_at' => $status === WorkshopRegistration::STATUS_CONFIRMED ? $statusAt : null,
            'waitlisted_at' => $status === WorkshopRegistration::STATUS_WAITLISTED ? $statusAt : null,
            'source' => WorkshopRegistration::SOURCE_PUBLIC,
        ]);
    }

    private function encodedPayload(string $email): string
    {
        return base64_encode(json_encode([
            'applicant_name' => 'Concurrent Applicant',
            'email' => $email,
            'phone' => '+880 1712 345678',
            'responses' => ['motivation' => 'Concurrency test'],
        ], JSON_THROW_ON_ERROR));
    }
}
