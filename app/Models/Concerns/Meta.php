<?php

namespace App\Models\Concerns;

use App\Support\Frontend;
use App\Support\Seo;
use App\Support\Sizes;
use Damoon\Schema\Concerns\HasSchemas;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Rankbeam\Seo\Traits\HasSEO;

/**
 * SEO and structured data for articles, pages, brands, and projects.
 *
 * The SEO box on the form fills seo_meta through rankbeam/laravel-seo. When a value is left
 * empty, the package falls back to what this trait gives it: the title, the first words of the
 * body, the large size of the cover, and the record's address on the public website from
 * App\Support\Frontend. Structured data comes from the record's own schema items (damoon/schema),
 * which start as the model's SCHEMAS with their default values, after the site-wide ones from the
 * general settings; a schema_jsonld stored in seo_meta replaces both.
 *
 * Extending:
 * - The model needs Body, a cover column, a SCHEMAS constant with its starting schema types, an
 *   entry in Frontend::ROUTES, a schemas JSON column, and implements Schemable.
 *   A model whose picture has another name overrides getSEOImage, like Brand with its logo.
 * - Add the model to Seo::MODELS so the media page sees its social image.
 *
 * @mixin Model
 *
 * @property string|null $slug
 * @property string|null $cover
 */
trait Meta
{
    use HasSchemas;
    use HasSEO;

    /**
     * The schema types a new record of this model starts with.
     *
     * @return list<string>
     */
    public static function blueprints(): array
    {
        return static::SCHEMAS;
    }

    /**
     * The body as plain text, shortened to what a search result shows.
     */
    public function getSEODescription(): ?string
    {
        $text = $this->getContentForSEO();

        return $text === '' ? null : Str::limit($text, 155);
    }

    /**
     * The large size of the cover, or the site default image.
     */
    public function getSEOImage(): ?string
    {
        if (is_string($this->cover) && $this->cover !== '') {
            return Seo::value(Sizes::pick($this->cover, 'large'));
        }

        return config('seo.default_og_image');
    }

    /**
     * The body as plain text with the spacing collapsed.
     */
    public function getContentForSEO(): string
    {
        if ($this->content === null) {
            return '';
        }

        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $this->html())), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * The address of the record on the public website.
     */
    public function getUrlForSEO(): string
    {
        return Frontend::link($this);
    }

    /**
     * The site-wide schemas, then this record's own.
     *
     * The SEO package asks for these only when the record has no schema_jsonld in seo_meta.
     *
     * @return list<array<string, mixed>>
     */
    public function getSEOSchema(): array
    {
        return [...(Frontend::settings()?->structured() ?? []), ...$this->structured()];
    }

    /**
     * The record placeholders: title, description, address, image, dates, author, and the type's name.
     *
     * The SEO box's own description and social image come first when they are filled.
     *
     * @return array<string, string>
     */
    public function placeholders(): array
    {
        $meta = $this->seoMeta;
        $image = filled($meta?->og_image) ? $meta->og_image : $this->getSEOImage();
        $author = method_exists($this, 'author') && $this->getAttribute('author_id') !== null ? $this->author : null;

        return [
            '{title}' => (string) $this->getSEOTitle(),
            '{description}' => (string) (filled($meta?->description) ? $meta->description : $this->getSEODescription()),
            '{url}' => $this->getUrlForSEO(),
            '{image}' => is_string($image) && $image !== '' ? (str_starts_with($image, '/') ? url($image) : $image) : '',
            '{published}' => self::atom($this->getAttribute('published_at') ?? $this->getAttribute('created_at')),
            '{modified}' => self::atom($this->getAttribute('updated_at')),
            '{author}' => $author !== null && method_exists($author, 'getFilamentName') ? $author->getFilamentName() : '',
            '{section}' => Frontend::label(static::class),
        ];
    }

    /**
     * The address of this type's list on the website, for the breadcrumb step after home.
     */
    public function section(): ?string
    {
        return Frontend::section(static::class);
    }

    /**
     * Parents of a record that sits in a tree, the farthest first, as [title, address].
     *
     * @return list<array{0: string, 1: string}>
     */
    public function crumbs(): array
    {
        if (! method_exists($this, 'parent')) {
            return [];
        }

        $list = [];
        $seen = [$this->getKey()];
        $parent = $this->getAttribute('parent_id') !== null ? $this->parent : null;

        while ($parent instanceof Model && ! in_array($parent->getKey(), $seen, true) && method_exists($parent, 'getUrlForSEO')) {
            array_unshift($list, [(string) $parent->getAttribute('title'), (string) $parent->getUrlForSEO()]);
            $seen[] = $parent->getKey();
            $parent = $parent->getAttribute('parent_id') !== null ? $parent->parent : null;
        }

        return $list;
    }

    /**
     * The record's own questions, for FAQPage.
     *
     * @return list<array{question?: string, answer?: string}>
     */
    public function faqs(): array
    {
        return array_values(array_filter((array) $this->getAttribute('questions'), 'is_array'));
    }

    /**
     * A date as ISO 8601, or an empty string.
     */
    private static function atom(mixed $value): string
    {
        return $value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : '';
    }
}
