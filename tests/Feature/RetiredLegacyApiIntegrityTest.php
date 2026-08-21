<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RetiredLegacyApiIntegrityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('retiredEndpointProvider')]
    public function test_retired_legacy_endpoints_keep_stable_empty_contracts(string $routeName, array $expectedData, array $expectedProperties): void
    {
        $this->getJson(route($routeName))
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'properties' => $expectedProperties,
                'data' => $expectedData,
            ]);
    }

    public static function retiredEndpointProvider(): array
    {
        $emptyPagination = ['page' => 1, 'total_page' => 1, 'total_count' => 0];

        return [
            'activities' => [
                'api.frontend.activities',
                ['activities' => []],
                $emptyPagination,
            ],
            'interactive audio' => [
                'api.frontend.interactiveAudio',
                ['interactive_radios' => []],
                $emptyPagination,
            ],
            'ALP package filters' => [
                'api.frontend.resources.alp-filter',
                ['alp_packages' => []],
                [],
            ],
            'teacher training type filters' => [
                'api.frontend.resources.training-type-filter',
                ['teacher_training_types' => []],
                [],
            ],
        ];
    }
}
