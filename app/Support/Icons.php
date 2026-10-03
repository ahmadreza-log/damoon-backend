<?php

namespace App\Support;

use BladeUI\Icons\Exceptions\SvgNotFound;
use BladeUI\Icons\Factory;
use Illuminate\Contracts\Foundation\Application;

/**
 * Icons for social links, through Blade Icons (blade-ui-kit/blade-icons).
 *
 * Three sets are offered: Simple Icons (prefix si, from codeat3/blade-simple-icons) for world
 * brands, the app's own brand set (prefix brand, resources/svg/brands) for Iranian messengers
 * and LinkedIn, and Heroicons outline (heroicon-o) for plain marks like email or phone. An icon
 * is stored by its full name, such as si-instagram, and turned into SVG markup with svg().
 * POPULAR lists the networks shown before anything is typed, with Persian names that search
 * also matches.
 *
 * Extending:
 * - A new icon file goes in resources/svg/brands; a new set is one more entry in SETS.
 * - A network worth listing first is one more entry in POPULAR.
 */
class Icons
{
    /** The app's own brand set name in Blade Icons. */
    public const BRANDS = 'brands';

    /** Searchable sets: Blade Icons set name => the name prefix searched in it. */
    public const SETS = [
        'simple-icons' => 'si-',
        self::BRANDS => 'brand-',
        'heroicons' => 'heroicon-o-',
    ];

    /** The networks listed first, with their Persian names. */
    public const POPULAR = [
        'si-instagram' => 'اینستاگرام',
        'si-telegram' => 'تلگرام',
        'si-whatsapp' => 'واتساپ',
        'brand-linkedin' => 'لینکدین',
        'si-x' => 'ایکس (توییتر)',
        'si-youtube' => 'یوتیوب',
        'si-aparat' => 'آپارات',
        'brand-eitaa' => 'ایتا',
        'brand-bale' => 'بله',
        'brand-rubika' => 'روبیکا',
        'brand-igap' => 'آی‌گپ',
        'si-facebook' => 'فیسبوک',
        'si-threads' => 'تردز',
        'si-tiktok' => 'تیک‌تاک',
        'si-pinterest' => 'پینترست',
        'si-github' => 'گیت‌هاب',
        'si-discord' => 'دیسکورد',
        'heroicon-o-envelope' => 'ایمیل',
        'heroicon-o-phone' => 'تلفن',
        'heroicon-o-globe-alt' => 'وب‌سایت',
    ];

    /** Most search results returned at once. */
    public const LIMIT = 50;

    /** Every searchable icon name, read once per request. */
    private static ?array $names = null;

    /**
     * Adds the app's brand set to Blade Icons. Called from AppServiceProvider::register.
     */
    public static function register(Application $app): void
    {
        $add = function (Factory $factory): void {
            $factory->add(self::BRANDS, [
                'path' => resource_path('svg/brands'),
                'prefix' => 'brand',
            ]);
        };

        if ($app->resolved(Factory::class)) {
            $add($app->make(Factory::class));

            return;
        }

        $app->afterResolving(Factory::class, $add);
    }

    /**
     * The popular networks as select options, each label with its icon.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (array_keys(self::POPULAR) as $name) {
            $options[$name] = self::label($name);
        }

        return $options;
    }

    /**
     * Icons whose name or Persian name contains the text, popular ones first.
     *
     * @return array<string, string>
     */
    public static function search(string $text): array
    {
        $text = mb_strtolower(trim($text));

        if ($text === '') {
            return self::options();
        }

        $found = [];

        foreach (self::POPULAR as $name => $persian) {
            if (str_contains($name, $text) || str_contains($persian, $text)) {
                $found[] = $name;
            }
        }

        foreach (self::names() as $name) {
            if (count($found) >= self::LIMIT) {
                break;
            }

            if (str_contains($name, $text) && ! in_array($name, $found, true)) {
                $found[] = $name;
            }
        }

        $options = [];

        foreach ($found as $name) {
            $options[$name] = self::label($name);
        }

        return $options;
    }

    /**
     * The option label: the icon, then its Persian name or its icon name.
     */
    public static function label(string $name): string
    {
        $svg = self::markup($name, 'dp-icon-svg') ?? '';

        return '<span class="dp-icon-option">'.$svg.'<span>'.e(self::POPULAR[$name] ?? $name).'</span></span>';
    }

    /**
     * Whether Blade Icons knows the icon and it is in one of the offered sets.
     */
    public static function exists(string $name): bool
    {
        return in_array($name, self::names(), true);
    }

    /**
     * The icon as SVG markup, or null when it is unknown.
     *
     * The icon always sits beside its name, so it is hidden from screen readers and its
     * own title is dropped.
     */
    public static function markup(string $name, string $class = ''): ?string
    {
        if (! self::exists($name)) {
            return null;
        }

        try {
            $svg = svg($name, $class, ['aria-hidden' => 'true'])->toHtml();
        } catch (SvgNotFound) {
            return null;
        }

        return (string) preg_replace('#<title>.*?</title>#s', '', $svg);
    }

    /**
     * Every icon name in the offered sets, such as si-instagram or brand-eitaa.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        if (self::$names !== null) {
            return self::$names;
        }

        $sets = app(Factory::class)->all();
        $names = [];

        foreach (self::SETS as $set => $prefix) {
            foreach ($sets[$set]['paths'] ?? [] as $path) {
                foreach (glob(rtrim($path, '/\\').'/*.svg') ?: [] as $file) {
                    $names[] = $set === 'heroicons'
                        ? self::outline(basename($file, '.svg'))
                        : $prefix.basename($file, '.svg');
                }
            }
        }

        return self::$names = array_values(array_unique(array_filter($names)));
    }

    /**
     * A Heroicons file name as its outline icon name; other styles are left out.
     */
    private static function outline(string $file): ?string
    {
        return str_starts_with($file, 'o-') ? 'heroicon-'.$file : null;
    }
}
