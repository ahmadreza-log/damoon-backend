<?php

namespace Tests;

use App\Http\Middleware\RequireApiKey;
use App\Models\ApiKey;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

/**
 * Base class for every test in the suite.
 *
 * It boots the Laravel application for each test through Laravel's own TestCase.
 * phpunit.xml runs the tests on an in-memory SQLite database with array cache and
 * sessions and a sync queue, so tests never touch the real PostgreSQL data.
 * When the database is migrated, every test sends a fresh API key for http://localhost in the
 * X-Api-Key header, so API tests reach /v1 the way the website does. ApiKeyTest calls
 * withoutHeader to check the API without one.
 *
 * Extending:
 * - Put helpers shared by many test classes here instead of copying them into each class.
 */
abstract class TestCase extends BaseTestCase
{
    /** The plain API key every request of this test sends. */
    protected ?string $apikey = null;

    /**
     * Sends the test API key on every request once the tables exist.
     *
     * PHPUnit owns this method name.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('api_keys')) {
            [, $this->apikey] = ApiKey::issue(['name' => 'تست', 'origin' => 'http://localhost']);

            $this->withHeader(RequireApiKey::HEADER, $this->apikey);
        }
    }
}
