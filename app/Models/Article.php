<?php

namespace App\Models;

use App\Filament\Blocks\Code;
use App\Support\Sizes;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A panel article in the content group.
 *
 * The slug is filled from the title when the form leaves it blank.
 * The body is stored as a Tiptap JSON document. html() renders it for the site.
 * Cover, gallery, and body pictures are media library files. They stay in the
 * library when the article is deleted, like WordPress, and are removed only from the media page.
 * New pictures get their smaller copies from Sizes when the article is saved.
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
    'seo_title',
    'seo_description',
    'questions',
    'gallery',
])]
class Article extends Model
{
    /**
     * Custom blocks the body editor offers and the renderer understands.
     *
     * @var array<int, class-string>
     */
    public const BLOCKS = [Code::class];

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
     * The body as a Tiptap JSON document.
     *
     * An HTML string is turned into the same JSON before it is stored, so older
     * callers and imports still work. Eloquent owns this method name.
     *
     * @return Attribute<array<string, mixed>|null, mixed>
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?array => is_string($value) ? json_decode($value, true) : null,
            set: fn (mixed $value): ?string => $value === null ? null : json_encode(self::document($value), JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * A Tiptap JSON document from stored JSON, an HTML string, or an array.
     *
     * @return array<string, mixed>
     */
    public static function document(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $value = (string) $value;
        $decoded = json_decode($value, true);

        if (is_array($decoded) && ($decoded['type'] ?? null) === 'doc') {
            return $decoded;
        }

        return RichContentRenderer::make($value)->customBlocks(self::BLOCKS)->toArray()
            ?: ['type' => 'doc', 'content' => []];
    }

    /**
     * The body as HTML for the site, with custom code blocks written out.
     *
     * The HTML is not sanitized, because code blocks must keep their tags.
     * Only staff who may edit articles write this content.
     */
    public function html(): string
    {
        return RichContentRenderer::make($this->content)
            ->customBlocks(self::BLOCKS)
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsVisibility('public')
            ->toUnsafeHtml();
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

    /**
     * The body document without the image nodes that point at one path.
     *
     * @return array<string, mixed>|mixed
     */
    public static function strip(mixed $content, string $path): mixed
    {
        if (! is_array($content) || ! isset($content['content']) || ! is_array($content['content'])) {
            return $content;
        }

        $kept = [];

        foreach ($content['content'] as $child) {
            if (is_array($child) && ($child['type'] ?? null) === 'image' && ($child['attrs']['id'] ?? null) === $path) {
                continue;
            }

            $kept[] = self::strip($child, $path);
        }

        $content['content'] = $kept;

        return $content;
    }

    /**
     * Public paths of pictures uploaded into a body document.
     *
     * The editor keeps the stored path in the id of each image node.
     * Images linked from other sites have no id and are skipped.
     *
     * @return array<int, string>
     */
    public static function images(mixed $content): array
    {
        if (! is_array($content)) {
            return [];
        }

        $paths = [];

        if (($content['type'] ?? null) === 'image') {
            $id = $content['attrs']['id'] ?? null;

            if (is_string($id) && str_starts_with($id, self::FOLDER.'/')) {
                $paths[] = $id;
            }
        }

        foreach ((array) ($content['content'] ?? []) as $child) {
            array_push($paths, ...self::images($child));
        }

        return $paths;
    }
}
