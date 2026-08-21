<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityRouteIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_cache_clear_backdoor_is_not_registered(): void
    {
        $this->get('/clear')->assertNotFound();
    }

    public function test_missing_content_returns_a_real_404_status(): void
    {
        $this->get('/this-page-must-never-exist')->assertNotFound();
    }
}
