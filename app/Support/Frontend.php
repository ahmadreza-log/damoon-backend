<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Brand;
use App\Models\Page;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;

/**
 * Addresses on the public website, which is not served by this Laravel app.
 *
 * The site address and the address pattern of each content type are set on the general
 * settings page. A pattern is a path with {slug}, such as /blog/{slug}; the part before the
 * first placeholder is the address of that type's list, used as a breadcrumb step. Without a
 * site address, the app's own address (APP_URL) is used. Media files stay on the app's address,
 * since this app serves them.
 *
 * One instance lives per request (scoped in AppServiceProvider), so the settings row is read
 * once; saving the settings forgets it.
 *
 * Extending:
 * - A new content type is one more entry in ROUTES; its model builds its address with link.
 */
class Frontend
{
    /** Each content type's key on the settings row, its Persian name, and its default pattern. */
    public const ROUTES = [
        Article::class => ['key' => 'articles', 'label' => 'نوشته‌ها', 'default' => '/articles/{slug}'],
        Page::class => ['key' => 'pages', 'label' => 'برگه‌ها', 'default' => '/{slug}'],
        Brand::class => ['key' => 'brands', 'label' => 'برندها', 'default' => '/brands/{slug}'],
        Project::class => ['key' => 'projects', 'label' => 'پروژه‌ها', 'default' => '/projects/{slug}'],
    ];

    /** The settings row, read on first use. */
    private ?Setting $settings = null;

    /** Whether the settings row has been read. */
    private bool $loaded = false;

    /**
     * The site address without a trailing slash.
     */
    public static function base(): string
    {
        $url = (string) self::settings()?->url;

        return rtrim($url !== '' ? $url : (string) config('app.url'), '/');
    }

    /**
     * A full address on the site for a path.
     */
    public static function url(string $path = ''): string
    {
        $path = trim($path, '/');

        return $path === '' ? self::base() : self::base().'/'.$path;
    }

    /**
     * The address pattern of a model class, from the settings or the default.
     */
    public static function pattern(string $model): string
    {
        $route = self::ROUTES[$model] ?? null;

        if ($route === null) {
            return '/{slug}';
        }

        $saved = self::settings()?->routes[$route['key']] ?? null;

        return is_string($saved) && str_contains($saved, '{slug}') ? $saved : $route['default'];
    }

    /**
     * A record's full address on the site.
     */
    public static function link(Model $record): string
    {
        return self::url(strtr(self::pattern($record::class), [
            '{slug}' => (string) $record->getAttribute('slug'),
            '{id}' => (string) $record->getKey(),
        ]));
    }

    /**
     * The address of the list a record's type sits under, or null when the pattern starts with a placeholder.
     */
    public static function section(string $model): ?string
    {
        $pattern = self::pattern($model);
        $head = trim(substr($pattern, 0, (int) strpos($pattern, '{')), '/');

        return $head === '' ? null : self::url($head);
    }

    /**
     * The Persian name of a model class's list, such as نوشته‌ها.
     */
    public static function label(string $model): string
    {
        return self::ROUTES[$model]['label'] ?? '';
    }

    /**
     * Each type's settings key with its Persian name and default pattern, for the settings form.
     *
     * @return array<string, array{label: string, default: string}>
     */
    public static function types(): array
    {
        $types = [];

        foreach (self::ROUTES as $route) {
            $types[$route['key']] = ['label' => $route['label'], 'default' => $route['default']];
        }

        return $types;
    }

    /**
     * The settings row of this request.
     */
    public static function settings(): ?Setting
    {
        $frontend = app(self::class);

        if (! $frontend->loaded) {
            $frontend->settings = Setting::current();
            $frontend->loaded = true;
        }

        return $frontend->settings;
    }
}
