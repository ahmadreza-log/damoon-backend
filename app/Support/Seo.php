<?php

namespace App\Support;

use App\Filament\Fields\MediaPicker;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Page;
use App\Models\Project;
use App\Models\Setting;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Rankbeam\Seo\Filament\Forms\SEOFields;
use Rankbeam\Seo\Services\SEOWarningEvaluator;

/**
 * The SEO box on article, page, brand, and project forms, from rankbeam/laravel-seo-filament.
 *
 * Each record keeps its SEO in one seo_meta row per locale through HasSEO. boot() fits
 * the package fields to this panel: title and description stop at the seo_meta column
 * lengths, and the social image is a media library picture instead of a separate upload.
 * That image is stored as /storage/{path}, so the package can turn it into a full address,
 * and path() gives back the public disk path the media library knows.
 *
 * Extending:
 * - A model gets the box with HasSEO on the model and HasSEOFields plus seoSection() on its resource.
 *   Add it to MODELS so the media page lists and clears its social image.
 * - Persian strings for the box live in lang/vendor/seo-filament/fa and lang/vendor/seo/fa.
 */
class Seo
{
    /** Models with the SEO box, and the Persian label used on the media page. */
    public const MODELS = [
        Article::class => 'نوشته',
        Page::class => 'برگه',
        Brand::class => 'برند',
        Project::class => 'پروژه',
    ];

    /** The public folder for social images uploaded from the SEO box. */
    public const FOLDER = 'seo';

    /** What goes before a public disk path in seo_meta.og_image. */
    public const PREFIX = '/storage/';

    /** Longest title and description the seo_meta columns hold. */
    public const TITLE = 70;

    public const DESCRIPTION = 160;

    /** Whether the field changes are registered; the package keeps them for the whole process. */
    private static bool $ready = false;

    /**
     * Registers the panel's changes to the package fields. Called from AppServiceProvider.
     */
    public static function boot(): void
    {
        if (self::$ready) {
            return;
        }

        self::$ready = true;

        SEOFields::modifyFieldUsing('title', fn (TextInput $field): TextInput => $field->maxLength(self::TITLE));
        SEOFields::modifyFieldUsing('description', fn (Textarea $field): Textarea => $field->maxLength(self::DESCRIPTION));
        SEOFields::modifyFieldUsing('og_image', fn (Field $field): MediaPicker => self::picker($field));
    }

    /**
     * Uses the panel brand as the site name in the SEO previews and the title suffix.
     *
     * Runs when the panel boots, since the brand is stored in the settings table.
     */
    public static function brand(): void
    {
        $name = Setting::brand();

        config(['seo.site_name' => $name, 'seo.title_suffix' => ' | '.$name]);
    }

    /**
     * The media picker that replaces the package's social image upload.
     */
    public static function picker(Field $field): MediaPicker
    {
        return MediaPicker::make('og_image')
            ->label($field->getLabel())
            ->helperText(__('seo-filament::seo-filament.fields.og_image_help', [
                'width' => SEOWarningEvaluator::IDEAL_SOCIAL_IMAGE_WIDTH,
                'height' => SEOWarningEvaluator::IDEAL_SOCIAL_IMAGE_HEIGHT,
            ]))
            ->directory(self::FOLDER)
            ->meta('seo_locale', $field->getMeta('seo_locale'))
            ->columnSpan(2)
            ->afterStateHydrated(function (MediaPicker $component, mixed $state): void {
                $component->state(self::path($state));
            })
            ->dehydrateStateUsing(fn (mixed $state): ?string => self::value($state));
    }

    /**
     * The public disk path inside a stored og_image, or null when it is not one of ours.
     */
    public static function path(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if (! is_string($value) || ! str_starts_with($value, self::PREFIX)) {
            return null;
        }

        $path = substr($value, strlen(self::PREFIX));

        return $path === '' ? null : $path;
    }

    /**
     * The og_image value stored for a public disk path, or null when there is none.
     */
    public static function value(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = reset($path);
        }

        return is_string($path) && $path !== '' ? self::PREFIX.$path : null;
    }
}
