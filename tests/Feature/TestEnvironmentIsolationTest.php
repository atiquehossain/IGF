<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestEnvironmentIsolationTest extends TestCase
{
    public function test_suite_cannot_boot_against_the_development_database(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertStringEndsWith(
            'storage/framework/testing-config.php',
            str_replace('\\', '/', app()->getCachedConfigPath())
        );
    }
}
