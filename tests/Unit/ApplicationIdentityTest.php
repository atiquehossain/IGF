<?php

namespace Tests\Unit;

use App\Support\ApplicationIdentity;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class ApplicationIdentityTest extends TestCase
{
    public function test_email_identity_is_normalized_and_keyed_before_lookup(): void
    {
        config(['app.key' => 'base64:test-application-key']);

        $this->assertSame('person@example.test', ApplicationIdentity::normalizeEmail('  Person@Example.Test '));
        $this->assertSame(
            ApplicationIdentity::emailHash('person@example.test'),
            ApplicationIdentity::emailHash(' PERSON@EXAMPLE.TEST ')
        );
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', ApplicationIdentity::emailHash('person@example.test'));
    }

    public function test_invalid_email_and_reference_kinds_fail_closed(): void
    {
        foreach (['', 'not-email', str_repeat('a', 250) . '@x.test'] as $email) {
            try {
                ApplicationIdentity::normalizeEmail($email);
                $this->fail('Invalid email was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        ApplicationIdentity::reference('donation');
    }

    public function test_references_are_opaque_kind_specific_and_date_stamped(): void
    {
        $date = Carbon::parse('2026-08-26 12:00:00');
        $job = ApplicationIdentity::reference('job', $date);
        $workshop = ApplicationIdentity::reference('workshop', $date);

        $this->assertMatchesRegularExpression('/^IGF-JOB-20260826-[A-Z0-9]{10}$/', $job);
        $this->assertMatchesRegularExpression('/^IGF-WS-20260826-[A-Z0-9]{10}$/', $workshop);
        $this->assertNotSame($job, ApplicationIdentity::reference('job', $date));
    }
}
