<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_application_bootstraps_the_http_router(): void
    {
        $this->assertTrue($this->app->bound('router'));
    }
}
