<?php

namespace App\Models\Concerns;

use App\Support\Seo;
use App\Support\Sizes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Rankbeam\Seo\Traits\HasSEO;

/**
 * SEO for articles and pages through rankbeam/laravel-seo.
 *
 * The SEO box on the form fills seo_meta. When a value is left empty, the package
 * falls back to what this trait gives it: the title, the first words of the body,
 * the large size of the cover, and the site address under ADDRESS.
 *
 * Extending:
 * - The model needs Body, a cover column, and an ADDRESS constant, the path its site pages live under.
 * - Add the model to Seo::MODELS so the media page sees its social image.
 *
 * @mixin Model
 *
 * @property string|null $slug
 * @property string|null $cover
 */
trait Meta
{
    use HasSEO;

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
     * The address of the record on the site.
     */
    public function getUrlForSEO(): string
    {
        return url(trim(static::ADDRESS.'/'.$this->slug, '/'));
    }
}
