<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laravel's sample feature test: a smoke test that the application boots and serves pages.
 *
 * Extending:
 * - Replace it with a real home page test once the public site is built.
 */
class ExampleTest extends TestCase
{
    /**
     * The home page answers with HTTP 200.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
