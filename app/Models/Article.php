<?php

namespace App\Models;

use App\Support\Sizes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

/**
 * A panel article in the content group.
 *
 * The slug is filled from the title when the form leaves it blank.
 * Cover and gallery files live on the public disk and are removed with the article.
 * New cover and gallery images get their smaller copies from Sizes when the article is saved.
 *
 * Extending:
 * - Add a column in the articles migration, Fillable, and ArticleResource together.
 * - A new relation belongs here and on the article form.
 */
#[Fillable([
    'title',
    'slug',
    'content',
    'cover',
    'category_id',
    'author_id',
    'published_at',
    'seo_title',
    'seo_description',
    'questions',
    'gallery',
])]
class Article extends Model
{
    /**
     * Fills the slug, builds image sizes, and drops stored images when the article is removed.
     *
     * Eloquent owns this method name.
     */
    protected static function booted(): void
    {
        static::saving(function (Article $article): void {
            $article->place();
        });

        static::saved(function (Article $article): void {
            $article->resize();
        });

        static::deleted(function (Article $article): void {
            $article->clear();
        });
    }

    /**
     * Turns a title or a typed slug into the stored key.
     */
    public static function link(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', '-', $value) ?? '';
        $value = preg_replace('/-+/u', '-', $value) ?? '';

        return trim($value, '-');
    }

    /**
     * The category shown on the article.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Tags attached to the article.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * The staff user chosen as the author.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Other articles listed as related.
     *
     * @return BelongsToMany<Article, $this>
     */
    public function related(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'article_related', 'article_id', 'related_id');
    }

    /**
     * Products listed as related.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
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
            'published_at' => 'datetime',
            'questions' => 'array',
            'gallery' => 'array',
        ];
    }

    /**
     * Stores a unique slug, using the title when the field is blank.
     */
    private function place(): void
    {
        $source = self::link((string) ($this->slug !== null && $this->slug !== '' ? $this->slug : $this->title));
        $base = $source !== '' ? $source : 'article';
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
     * Builds sizes for cover and gallery images that were not on the article before this save.
     *
     * Runs in saved, while getOriginal still holds the previous values.
     */
    private function resize(): void
    {
        $before = self::pictures($this->getOriginal('cover'), $this->getOriginal('gallery'));

        foreach (array_diff(self::pictures($this->cover, $this->gallery), $before) as $path) {
            Sizes::make($path);
        }
    }

    /**
     * Removes the cover and gallery files and their sizes from the public disk.
     */
    private function clear(): void
    {
        $paths = self::pictures($this->cover, $this->gallery);

        if ($paths === []) {
            return;
        }

        Storage::disk('public')->delete($paths);

        foreach ($paths as $path) {
            Sizes::drop($path);
        }
    }

    /**
     * Cover and gallery paths as one flat list.
     *
     * @return array<int, string>
     */
    private static function pictures(mixed $cover, mixed $gallery): array
    {
        $paths = [];

        if (is_string($cover) && $cover !== '') {
            $paths[] = $cover;
        }

        foreach ((array) $gallery as $path) {
            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
