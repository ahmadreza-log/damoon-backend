<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base class for every test in the suite.
 *
 * It boots the Laravel application for each test through Laravel's own TestCase.
 * phpunit.xml runs the tests on an in-memory SQLite database with array cache and
 * sessions and a sync queue, so tests never touch the real PostgreSQL data.
 *
 * Extending:
 * - Put helpers shared by many test classes here instead of copying them into each class.
 */
abstract class TestCase extends BaseTestCase
{
    //
}
