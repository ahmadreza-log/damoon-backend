<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Laravel's sample unit test.
 *
 * Unit tests extend PHPUnit's TestCase directly, so they run without booting Laravel
 * and cannot use the database, facades, or config.
 *
 * Extending:
 * - Put tests of plain classes here, such as App\Rules\National or App\Support\Shamsi.
 */
class ExampleTest extends TestCase
{
    /**
     * Always passes; it only proves the unit suite runs.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }
}
