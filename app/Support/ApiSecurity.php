<?php

namespace App\Support;

use App\Http\Middleware\AuthenticateCustomerToken;
use App\Http\Middleware\RequireApiKey;
use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Configuration\SecurityDocumentationContext;
use Dedoc\Scramble\Contracts\SecurityDocumentationStrategy;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * How /docs/api shows the API's security: the X-Api-Key header everywhere, plus a Bearer
 * token on customer routes.
 *
 * Set as security_strategy in config/scramble.php. Every route behind api.key asks for the key
 * (scheme "apiKey"); routes that also have auth.customer ask for the key and the customer token
 * together (scheme "bearer"). A route with neither is marked public.
 *
 * Extending:
 * - A new kind of route protection is a new scheme in configure and a new requirement in requirement.
 */
class ApiSecurity implements SecurityDocumentationStrategy
{
    /** Middleware that asks for the API key. */
    private const KEY = ['api.key', RequireApiKey::class];

    /** Middleware that asks for the customer token. */
    private const CUSTOMER = ['auth.customer', AuthenticateCustomerToken::class];

    /**
     * Adds both schemes to the document and the right requirement to each operation.
     *
     * Scramble owns this method name.
     */
    public function configure(SecurityDocumentationContext $context): GeneratorConfig
    {
        return $context->config
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->components->addSecurityScheme('apiKey', SecurityScheme::apiKey('header', RequireApiKey::HEADER)
                    ->as('apiKey')
                    ->setDescription('کلید خصوصی از صفحهٔ «تنظیمات API» پنل. درخواست مرورگر باید از همان دامنهٔ کلید باشد.'));
                $openApi->components->addSecurityScheme('bearer', SecurityScheme::http('bearer')
                    ->as('bearer')
                    ->setDescription('توکن مشتری از POST /v1/auth/login.'));
                $openApi->security = [new SecurityRequirement(['apiKey' => []])];
            })
            ->withOperationTransformers(function (OperationTransformers $transformers): void {
                $transformers->prepend(function (Operation $operation, RouteInfo $info): void {
                    $operation->security = $this->requirement($info->route);
                });
            });
    }

    /**
     * The security list of one route.
     *
     * @return list<SecurityRequirement>
     */
    private function requirement(Route $route): array
    {
        $middleware = $route->gatherMiddleware();
        $key = self::has($middleware, self::KEY);
        $customer = self::has($middleware, self::CUSTOMER);

        if (! $key && ! $customer) {
            return [];
        }

        return [new SecurityRequirement(array_merge(
            $key ? ['apiKey' => []] : [],
            $customer ? ['bearer' => []] : [],
        ))];
    }

    /**
     * Whether any of the route's middleware is in the list.
     *
     * @param  array<int, mixed>  $middleware
     * @param  list<string>  $names
     */
    private static function has(array $middleware, array $names): bool
    {
        return collect($middleware)
            ->some(fn (mixed $item): bool => is_string($item) && Str::is($names, $item));
    }
}
