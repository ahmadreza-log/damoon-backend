<?php

namespace App\Models;

use App\Filament\Blocks\Code;
use App\Models\Concerns\Body;
use App\Models\Concerns\Meta;
use App\Support\Sizes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A panel article in the content group.
 *
 * The slug is filled from the title when the form leaves it blank.
 * The body is stored as a Tiptap JSON document through Body. html() renders it for the site.
 * Cover, gallery, and body pictures are media library files. They stay in the
 * library when the article is deleted, like WordPress, and are removed only from the media page.
 * New pictures get their smaller copies from Sizes when the article is saved.
 * SEO title, description, and social image live in seo_meta through Meta.
 * commentable says whether visitors may send comments; it is on by default.
 * Comments are Comment rows and leave with the article.
 *
 * Extending:
 * - Add a column in the articles migration, Fillable, and ArticleResource together.
 * - A new relation belongs here and on the article form.
 * - A new editor block goes in BLOCKS. The form and html() both read that list.
 */
#[Fillable([
    'title',
    'slug',
    'content',
    'cover',
    'author_id',
    'published_at',
    'questions',
    'gallery',
    'commentable',
])]
class Article extends Model
{
    use Body;
    use Meta;

    /**
     * Custom blocks the body editor offers and the renderer understands.
     *
     * @var array<int, class-string>
     */
    public const BLOCKS = [Code::class];

    /** The site path articles live under, used for the SEO address. */
    public const ADDRESS = 'articles';

    /** The public folder for pictures uploaded inside the body editor. */
    public const FOLDER = 'articles/content';

    /**
     * Fills the slug and builds sizes for new pictures.
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
            $article->comments()->delete();
        });
    }

    /**
     * Visitor comments on this article, of every status.
     *
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'subject');
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
     * Articles the site may show: those with a publish date that has come.
     *
     * @param  Builder<Article>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('published_at', '<=', now());
    }

    /**
     * Categories the article sits in. An article can have more than one.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
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
            'commentable' => 'boolean',
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
        $before = self::pictures($this->getOriginal('cover'), $this->getOriginal('gallery'), $this->getOriginal('content'));

        foreach (array_diff(self::pictures($this->cover, $this->gallery, $this->content), $before) as $path) {
            Sizes::ensure($path);
        }
    }

    /**
     * Cover, gallery, and body picture paths as one flat list.
     *
     * @return array<int, string>
     */
    private static function pictures(mixed $cover, mixed $gallery, mixed $content = null): array
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

        return array_values(array_unique([...$paths, ...self::images($content)]));
    }
}
