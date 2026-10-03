<?php

namespace App\Models;

use App\Filament\Blocks\Code;
use App\Models\Concerns\Body;
use App\Models\Concerns\Meta;
use App\Support\Seo;
use App\Support\Sizes;
use Damoon\Schema\Contracts\Schemable;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A brand (برند) in the content group, such as a maker whose products the site sells.
 *
 * A brand has a Persian title, a slug filled from the title when the form leaves it blank,
 * an English title, a description stored as Tiptap JSON through Body, a list of features,
 * links to its website, LinkedIn, and software download, a catalog file, and a logo.
 * The logo and catalog are media library files: they stay in the library when the brand
 * is deleted and are removed only from the media page. A new logo or description picture
 * gets its sizes from Sizes when the brand is saved. SEO title, description, and social
 * image live in seo_meta through Meta; the logo stands in for the cover there.
 * commentable says whether visitors may send comments; it is on by default. Comments
 * are Comment rows and leave with the brand. A brand has no publish date, so every
 * brand counts as published.
 *
 * Extending:
 * - Add a column in a brands migration, Fillable, and BrandResource together.
 * - A new column that stores a public path belongs in Library::uses and Library::drop.
 */
#[Fillable([
    'title',
    'slug',
    'english',
    'content',
    'features',
    'website',
    'linkedin',
    'catalog',
    'download',
    'logo',
    'commentable',
    'schemas',
])]
class Brand extends Model implements Schemable
{
    use Body;

    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    use Meta;

    /**
     * Custom blocks the description editor offers and the renderer understands.
     *
     * @var array<int, class-string>
     */
    public const BLOCKS = [Code::class];

    /** The schema types a new brand starts with. */
    public const SCHEMAS = ['Brand', 'BreadcrumbList'];

    /** The public folder for pictures uploaded inside the description editor. */
    public const FOLDER = 'brands/content';

    /** The public folder for logos uploaded from the brand form. */
    public const LOGOS = 'brands/logos';

    /** The public folder for catalogs uploaded from the brand form. */
    public const CATALOGS = 'brands/catalogs';

    /** File types the catalog upload accepts. */
    public const TYPES = ['application/pdf'];

    /** Largest catalog upload in kilobytes. */
    public const WEIGHT = 20480;

    /**
     * Fills the slug and builds sizes for new pictures.
     *
     * Eloquent owns this method name.
     */
    protected static function booted(): void
    {
        static::saving(function (Brand $brand): void {
            $brand->place();
        });

        static::saved(function (Brand $brand): void {
            $brand->resize();
        });

        static::deleted(function (Brand $brand): void {
            $brand->comments()->delete();
        });
    }

    /**
     * Visitor comments on this brand, of every status.
     *
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'subject');
    }

    /**
     * Brands the site may show: all of them, since a brand has no publish date.
     *
     * Kept so comments can treat every commentable model the same way.
     *
     * @param  Builder<Brand>  $query
     */
    #[Scope]
    protected function published(Builder $query): void {}

    /**
     * The large size of the logo, or the site default image, for the SEO social image.
     */
    public function getSEOImage(): ?string
    {
        if (is_string($this->logo) && $this->logo !== '') {
            return Seo::value(Sizes::pick($this->logo, 'large'));
        }

        return config('seo.default_og_image');
    }

    /**
     * Column casts.
     *
     * Eloquent owns this method name.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'commentable' => 'boolean',
            'schemas' => 'array',
        ];
    }

    /**
     * Stores a unique slug, using the title when the field is blank.
     */
    private function place(): void
    {
        $source = Article::link((string) ($this->slug !== null && $this->slug !== '' ? $this->slug : $this->title));
        $base = $source !== '' ? $source : 'brand';
        $slug = $base;
        $count = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
                ->exists()
        ) {
            $slug = $base.'-'.$count;
            $count++;
        }

        $this->slug = $slug;
    }

    /**
     * Builds sizes for the logo and description pictures that were not on the brand before this save.
     *
     * Runs in saved, while getOriginal still holds the previous values.
     */
    private function resize(): void
    {
        $before = self::pictures($this->getOriginal('logo'), $this->getOriginal('content'));

        foreach (array_diff(self::pictures($this->logo, $this->content), $before) as $path) {
            Sizes::ensure($path);
        }
    }

    /**
     * Logo and description picture paths as one flat list.
     *
     * @return array<int, string>
     */
    private static function pictures(mixed $logo, mixed $content): array
    {
        $paths = is_string($logo) && $logo !== '' ? [$logo] : [];

        return array_values(array_unique([...$paths, ...self::images($content)]));
    }
}
