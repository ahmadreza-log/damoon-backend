<?php

use App\Support\ApiSecurity;
use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

/**
 * OpenAPI docs for the customer API.
 *
 * In the local environment the UI is at /docs/api and the specification is at /docs/api.json.
 * API routes have no /api prefix. They all live under /v1, so api_path is v1.
 * Register a new route in the v1 group in routes/api.php and it will show up here.
 */

return [
    /*
     * Which routes to document. String or array form; use Scramble::routes() for custom selection.
     *
     * 'api_path' => [
     *     'include' => 'api',
     *     'exclude' => ['api/internal'],
     * ],
     *
     * Without *, patterns match path segments (api matches api and api/users, not apiary).
     * With *, Str::is is used (e.g. api/v*).
     *
     * One static include → default server is /{include} and paths are stripped (/users).
     * Multiple includes or wildcards → server defaults to / and paths stay full (/api/users).
     * Override with `servers`, or use Scramble::registerApi() for separate bases.
     */
    'api_path' => 'v1',

    /*
     * Your API domain. By default, app domain is used. This is also a part of the default API routes
     * matcher, so when implementing your own, make sure you use this config if needed.
     */
    'api_domain' => null,

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => 'api.json',

    /*
     * Cache configuration for the generated OpenAPI document.
     *
     * Use `scramble:cache` to warm the cache and `scramble:clear` to invalidate it.
     */
    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        /*
         * API version.
         */
        'version' => env('API_VERSION', '1.0.0'),

        /*
         * Description rendered on the home page of the API documentation (`/docs/api`).
         */
        'description' => 'API دامون. هر درخواست باید سرآیند X-Api-Key با یک کلید از صفحهٔ «تنظیمات API» پنل داشته باشد؛ بدون کلید پاسخ ۴۰۱ است. هر کلید برای یک دامنه ساخته می‌شود و درخواست مرورگر از دامنهٔ دیگر پاسخ ۴۰۳ می‌گیرد. درخواست سرور (بدون سرآیند Origin) فقط با کلید پذیرفته می‌شود؛ پس کلید را در کد سمت سرور نگه دارید. نوشته‌ها، برگه‌ها، برندها، پروژه‌ها، فرم‌ها، دسته‌بندی‌ها، برچسب‌ها و رسانه‌ها عمومی و فقط‌خواندنی هستند و از نوشته‌ها و برگه‌ها فقط محتوای منتشرشده را می‌دهند. فهرست نوشته‌ها، برندها، پروژه‌ها، رسانه‌ها و دیدگاه‌ها همیشه صفحه‌بندی‌شده است. فهرست برگه‌ها، فرم‌ها، دسته‌بندی‌ها و برچسب‌ها به‌طور پیش‌فرض کامل برمی‌گردد و با فرستادن page یا per_page (تا ۱۰۰) صفحه‌به‌صفحه می‌شود. پاسخ صفحه‌بندی‌شده کنار data دو بخش links و meta هم دارد. دیدگاه‌های تأییدشدهٔ هر نوشته، برگه، برند و پروژه با GET /v1/{type}/{slug}/comments خوانده می‌شوند؛ type جایی است که دیدگاه در آن ثبت می‌شود و فعلاً articles، pages، brands یا projects است. ارسال دیدگاه با POST به همین نشانی‌ها نیازی به توکن ندارد، تا وقتی «اجازه به ارسال دیدگاه» روشن است پذیرفته می‌شود، و پس از تأیید در پنل نمایش داده می‌شود. هر فرم با GET /v1/forms/{slug} فیلدهایش را می‌دهد تا سایت آن را بسازد، و پاسخ‌ها با POST به همین نشانی و با کلید هر فیلد فرستاده می‌شوند؛ خطاها فارسی و با کلید فیلد برمی‌گردند و پیام در صندوق پیام‌های پنل می‌نشیند. ورود مشتری با POST /v1/auth/login توکن Bearer می‌دهد. با این توکن نام و ایمیل دیدگاه از حساب مشتری پر می‌شود. این توکن پنل کارکنان را باز نمی‌کند.',
    ],

    'ui' => [
        'title' => 'دامون',
    ],

    /*
     * Load Scramble's development tools on documentation pages. An explicit
     * SCRAMBLE_DEV_TOOLS value takes precedence over APP_DEBUG.
     */
    'dev_tools' => [
        'enabled' => env('SCRAMBLE_DEV_TOOLS', env('APP_DEBUG', false)),
    ],

    'renderer' => 'elements',

    'renderers' => [
        /*
         * Stoplight Elements config options: https://docs.stoplight.io/docs/elements/b074dc47b2826-elements-configuration-options
         */
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
        /*
         * Scalar API reference config options: https://scalar.com/products/api-references/configuration
         */
        'scalar' => [
            'view' => 'scramble::scalar',
            'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference',
            'theme' => 'laravel',
            'proxyUrl' => 'https://proxy.scalar.com',
            'darkMode' => false,
            'showDeveloperTools' => 'never',
            'agent' => ['disabled' => true],
            'credentials' => 'include',
        ],
    ],

    /*
     * The list of servers of the API. By default, when `null`, server URL will be created from
     * `scramble.api_path` and `scramble.api_domain` config variables. When providing an array, you
     * will need to specify the local server URL manually (if needed).
     *
     * Example of non-default config (final URLs are generated using Laravel `url` helper):
     *
     * ```php
     * 'servers' => [
     *     'Live' => 'api',
     *     'Prod' => 'https://scramble.dedoc.co/api',
     * ],
     * ```
     */
    'servers' => null,

    /**
     * Determines how Scramble stores the descriptions of enum cases.
     * Available options:
     * - 'description' – Case descriptions are stored as the enum schema's description using table formatting.
     * - 'extension' – Case descriptions are stored in the `x-enumDescriptions` enum schema extension.
     *
     *    @see https://redocly.com/docs-legacy/api-reference-docs/specification-extensions/x-enum-descriptions
     * - false - Case descriptions are ignored.
     */
    'enum_cases_description_strategy' => 'description',

    /**
     * Determines how Scramble stores the names of enum cases.
     * Available options:
     * - 'names' – Case names are stored in the `x-enumNames` enum schema extension.
     * - 'varnames' - Case names are stored in the `x-enum-varnames` enum schema extension.
     * - false - Case names are not stored.
     */
    'enum_cases_names_strategy' => false,

    /**
     * When Scramble encounters deep objects in query parameters, it flattens the parameters so the generated
     * OpenAPI document correctly describes the API. Flattening deep query parameters is relevant until
     * OpenAPI 3.2 is released and query string structure can be described properly.
     *
     * For example, this nested validation rule describes the object with `bar` property:
     * `['foo.bar' => ['required', 'int']]`.
     *
     * When `flatten_deep_query_parameters` is `true`, Scramble will document the parameter like so:
     * `{"name":"foo[bar]", "schema":{"type":"int"}, "required":true}`.
     *
     * When `flatten_deep_query_parameters` is `false`, Scramble will document the parameter like so:
     *  `{"name":"foo", "schema": {"type":"object", "properties":{"bar":{"type": "int"}}, "required": ["bar"]}, "required":true}`.
     */
    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],

    /*
     * Automatically document API security (OpenAPI `security` / `securitySchemes`) based on route
     * middleware.
     *
     * Disabled by default. Uncomment the line below to enable `MiddlewareAuthSecurityStrategy`.
     * When at least one documented route uses middleware matching the configured patterns (by default
     * `auth` and `auth:*`), bearer auth is applied globally. Routes without matching middleware are
     * marked as public (`security: []`).
     *
     * Set to `null` explicitly to disable. If you already configure security manually via
     * `afterOpenApiGenerated` / `extendOpenApi`, keep this disabled to avoid duplicate schemes.
     *
     * Customize with a class-string or [class, options]:
     *
     * 'security_strategy' => [
     *     \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
     *     [
     *         'middleware' => ['auth', 'auth:*'],
     *         'scheme' => \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer'),
     *     ],
     * ],
     */
    // Every /v1 route asks for the X-Api-Key header; customer routes ask for a Bearer token too.
    'security_strategy' => ApiSecurity::class,
];
