<?php

namespace Tests\Feature;

use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Tests\TestCase;

/**
 * Covers the OpenAPI document that Scramble builds for the customer API.
 *
 * Scramble reads the routes and controllers and publishes the document at /docs/api.
 * Paths in the document are relative to the v1 prefix, so /v1/auth/login is listed
 * as /auth/login.
 *
 * Extending:
 * - Add an assertContains here for each new customer route so the docs cannot silently lose it.
 */
class OpenApiTest extends TestCase
{
    /**
     * The document lists login, me, and the public content routes, leaves out routes that do not exist, and has version 1.0.0.
     * Every route asks for the X-Api-Key header, and me asks for the customer Bearer token as well.
     *
     * setThrowExceptions(true) makes the test fail when Scramble cannot read a route,
     * instead of silently dropping that route from the document.
     */
    public function test_customer_routes_are_in_the_openapi_document(): void
    {
        $spec = app(Generator::class)
            ->setThrowExceptions(true)
            ->generate(Scramble::getGeneratorConfig(Scramble::DEFAULT_API))
            ->spec();

        $paths = array_keys($spec['paths'] ?? []);

        $this->assertContains('/auth/login', $paths);
        $this->assertContains('/auth/me', $paths);

        foreach (['articles', 'pages', 'brands', 'projects', 'galleries', 'forms', 'categories', 'tags'] as $group) {
            $this->assertContains('/'.$group, $paths);
            $this->assertContains('/'.$group.'/{slug}', $paths);
        }

        $this->assertContains('/media', $paths);
        $this->assertContains('/media/{key}', $paths);
        $this->assertContains('/schema', $paths);
        $this->assertContains('/socials', $paths);
        $this->assertSame([['apiKey' => []]], $spec['paths']['/articles']['get']['security'] ?? null);
        $this->assertSame([['apiKey' => [], 'bearer' => []]], $spec['paths']['/auth/me']['get']['security'] ?? null);
        $this->assertSame(['type' => 'apiKey', 'in' => 'header', 'name' => 'X-Api-Key'], array_intersect_key(
            $spec['components']['securitySchemes']['apiKey'] ?? [],
            array_flip(['type', 'in', 'name']),
        ));
        $this->assertNotContains('/auth/register', $paths);
        $this->assertNotContains('/auth/verify', $paths);
        $this->assertNotContains('/auth/forgot', $paths);
        $this->assertSame('1.0.0', $spec['info']['version']);
    }
}
