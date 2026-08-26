<?php

namespace Tests\Unit;

use App\Services\PublicFormTokenService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicFormTokenServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_token_is_bound_to_listing_kind_and_a_human_submission_window(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');
        $service = app(PublicFormTokenService::class);
        $token = $service->issue('job', 'listing-uuid');

        Carbon::setTestNow('2026-08-26 12:00:02');
        $service->assertValid($token, 'job', 'listing-uuid', null);
        $this->addToAssertionCount(1);

        foreach ([
            ['workshop', 'listing-uuid', null],
            ['job', 'other-listing', null],
            ['job', 'listing-uuid', 'spam-link'],
        ] as [$kind, $listing, $honeypot]) {
            try {
                $service->assertValid($token, $kind, $listing, $honeypot);
                $this->fail('Tampered or spam submission token was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('submission', $exception->errors());
            }
        }
    }

    public function test_token_rejects_instant_expired_and_tampered_submissions(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');
        $service = app(PublicFormTokenService::class);
        $token = $service->issue('workshop', 'workshop-uuid');

        foreach ([
            ['2026-08-26 12:00:00', $token],
            ['2026-08-26 16:00:01', $token],
            ['2026-08-26 12:00:02', $token . 'x'],
        ] as [$now, $candidate]) {
            Carbon::setTestNow($now);
            try {
                $service->assertValid($candidate, 'workshop', 'workshop-uuid', '');
                $this->fail('Invalid form token was accepted.');
            } catch (ValidationException $exception) {
                $this->assertSame(
                    'This form session is no longer valid. Refresh the page and try again.',
                    $exception->errors()['submission'][0]
                );
            }
        }
    }
}
