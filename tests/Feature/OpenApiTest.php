<?php

namespace Tests\Feature;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Tests\TestCase;

class OpenApiTest extends TestCase
{
    public function test_customer_routes_are_in_the_openapi_document(): void
    {
        $spec = app(Generator::class)
            ->setThrowExceptions(true)
            ->generate(Scramble::getGeneratorConfig(Scramble::DEFAULT_API))
            ->spec();

        $paths = array_keys($spec['paths'] ?? []);

        $this->assertContains('/auth/login', $paths);
        $this->assertContains('/auth/me', $paths);
        $this->assertNotContains('/auth/register', $paths);
        $this->assertNotContains('/auth/verify', $paths);
        $this->assertNotContains('/auth/forgot', $paths);
        $this->assertSame('1.0.0', $spec['info']['version']);
    }
}
