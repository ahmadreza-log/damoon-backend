<?php

namespace App\Models;

use App\Filament\Blocks\Code;
use App\Filament\Builder;
use App\Models\Concerns\Body;
use App\Models\Concerns\Meta;
use App\Models\Concerns\Tree;
use App\Support\Sizes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder as Query;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Redberry\PageBuilderPlugin\Models\PageBuilderBlock;
use Redberry\PageBuilderPlugin\Traits\HasPageBuilder;

/**
 * A standalone site page in the content group, such as درباره ما or تماس با ما.
 *
 * Unlike an article, a page has no categories, tags, or related items. It can sit
 * under a parent page, and position orders pages that share a parent, like WordPress.
 * The slug is filled from the title when the form leaves it blank.
 * The body is a Tiptap JSON document through Body, and parent, children, family,
 * and trail come from Tree. The cover and body pictures are media library files.
 * They stay in the library when the page is deleted, and new ones get their sizes
 * from Sizes when the page is saved.
 *
 * Besides the body, a page can be laid out with the page builder (صفحه‌ساز): an ordered
 * list of blocks such as a hero banner, a gallery, or questions, stored in
 * page_builder_blocks through HasPageBuilder. layout draws them for the site, and
 * deleting the page deletes its blocks. SEO title, description, and social image
 * live in seo_meta through Meta.
 *
 * Extending:
 * - Add a column in a pages migration, Fillable, and PageResource together.
 * - A new editor block goes in BLOCKS. A new page builder block goes in BUILDER.
 * - A new column that stores a public path belongs in Library::uses and Library::drop.
 */
#[Fillable([
    'title',
    'slug',
    'content',
    'cover',
    'parent_id',
    'position',
    'author_id',
    'published_at',
])]
class Page extends Model
{
    use Body;
    use HasPageBuilder;
    use Meta;
    use Tree;

    /**
     * Custom blocks the body editor offers and the renderer understands.
     *
     * @var array<int, class-string>
     */
    public const BLOCKS = [Code::class];

    /**
     * Blocks the page builder offers, in the order they are listed.
     *
     * @var array<int, class-string<Builder\Block>>
     */
    public const BUILDER = [
        Builder\Hero::class,
        Builder\Text::class,
        Builder\Image::class,
        Builder\Gallery::class,
        Builder\Features::class,
        Builder\Callout::class,
        Builder\Faq::class,
        Builder\Video::class,
        Builder\Posts::class,
        Builder\Code::class,
    ];

    /** The public folder for pictures uploaded inside the body editor. */
    public const FOLDER = 'pages/content';

    /** The public folder for cover images uploaded from the page form. */
    public const COVERS = 'pages/covers';

    /** The site path pages live under, used for the SEO address. Pages sit at the site root. */
    public const ADDRESS = '';

    /**
     * Fills the slug, builds sizes for new pictures, and deletes the blocks with the page.
     *
     * Eloquent owns this method name.
     */
    protected static function booted(): void
    {
        static::saving(function (Page $page): void {
            $page->place();
        });

        static::saved(function (Page $page): void {
            $page->resize();
        });

        static::deleting(function (Page $page): void {
            $page->pageBuilderBlocks()->delete();
        });
    }

    /**
     * The page builder blocks drawn as HTML for the site, in page order.
     *
     * A block whose class is no longer in BUILDER is skipped, so removing a block type
     * never breaks old pages.
     */
    public function layout(): string
    {
        return $this->pageBuilderBlocks
            ->sortBy('order')
            ->map(fn (PageBuilderBlock $block): string => self::draw($block))
            ->filter()
            ->implode("\n");
    }

    /**
     * One page builder block drawn as HTML for the site, or an empty string when its class is not in BUILDER.
     */
    public static function draw(PageBuilderBlock $block): string
    {
        $type = $block->block_type;

        if (! in_array($type, self::BUILDER, true)) {
            return '';
        }

        return view($type::getView(), [
            'block' => [
                'id' => $block->id,
                'block_name' => $type::getBlockName(),
                'block_type' => $type,
                'data' => $type::formatForSingleView($block->data ?? []),
            ],
            'preview' => false,
        ])->render();
    }

    /**
     * Pages the site may show: those with a publish date that has come.
     *
     * @param  Query<Page>  $query
     */
    #[Scope]
    protected function published(Query $query): void
    {
        $query->where('published_at', '<=', now());
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
     * The title, shown for this level of the trail.
     */
    public function caption(): string
    {
        return (string) $this->title;
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
            'position' => 'integer',
        ];
    }

    /**
     * Stores a unique slug, using the title when the field is blank.
     */
    private function place(): void
    {
        $source = Article::link((string) ($this->slug !== null && $this->slug !== '' ? $this->slug : $this->title));
        $base = $source !== '' ? $source : 'page';
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
     * Builds sizes for the cover and body pictures that were not on the page before this save.
     *
     * Runs in saved, while getOriginal still holds the previous values.
     */
    private function resize(): void
    {
        $before = self::pictures($this->getOriginal('cover'), $this->getOriginal('content'));

        foreach (array_diff(self::pictures($this->cover, $this->content), $before) as $path) {
            Sizes::ensure($path);
        }
    }

    /**
     * Cover and body picture paths as one flat list.
     *
     * @return array<int, string>
     */
    private static function pictures(mixed $cover, mixed $content): array
    {
        $paths = is_string($cover) && $cover !== '' ? [$cover] : [];

        return array_values(array_unique([...$paths, ...self::images($content)]));
    }
}
