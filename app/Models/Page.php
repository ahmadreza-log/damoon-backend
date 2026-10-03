<?php

namespace App\Models;

use App\Filament\Blocks\Code;
use App\Models\Concerns\Body;
use App\Models\Concerns\Meta;
use App\Models\Concerns\Tree;
use App\Support\Library;
use App\Support\Sizes;
use Damoon\Schema\Contracts\Schemable;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder as Query;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
 * Besides the body, a page can be laid out with the page builder (صفحه‌ساز), a drag and
 * drop editor like Elementor built on GrapesJS. design keeps the editor's project:
 * its component tree and style rules as JSON. markup and style are the HTML and CSS the
 * editor exports on save, and layout joins them for the site. Pictures in the builder
 * are media library files, written as /storage/{path}. SEO title, description, and
 * social image live in seo_meta through Meta.
 * commentable says whether visitors may send comments; it is on by default.
 * Comments are Comment rows and leave with the page.
 *
 * Extending:
 * - Add a column in a pages migration, Fillable, and PageResource together.
 * - A new editor block goes in BLOCKS. A new page builder block goes in resources/js/designer.js.
 * - A new column that stores a public path belongs in Library::uses and Library::drop.
 */
#[Fillable([
    'title',
    'slug',
    'content',
    'design',
    'markup',
    'style',
    'cover',
    'parent_id',
    'position',
    'author_id',
    'published_at',
    'commentable',
    'schemas',
])]
class Page extends Model implements Schemable
{
    use Body;

    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use Meta;
    use Tree;

    /**
     * Custom blocks the body editor offers and the renderer understands.
     *
     * @var array<int, class-string>
     */
    public const BLOCKS = [Code::class];

    /** The public folder for pictures uploaded inside the body editor. */
    public const FOLDER = 'pages/content';

    /** The public folder for cover images uploaded from the page form. */
    public const COVERS = 'pages/covers';

    /** The public folder for pictures uploaded inside the page builder. */
    public const DESIGNS = 'pages/design';

    /** The schema types a new page starts with. */
    public const SCHEMAS = ['WebPage', 'BreadcrumbList'];

    /**
     * Fills the slug and builds sizes for new pictures.
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

        static::deleted(function (Page $page): void {
            $page->comments()->delete();
        });
    }

    /**
     * Visitor comments on this page, of every status.
     *
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'subject');
    }

    /**
     * The page builder layout for the site: its CSS in a style tag, then its HTML.
     */
    public function layout(): string
    {
        $style = trim((string) $this->style);

        return ($style === '' ? '' : '<style>'.$style.'</style>'."\n").(string) $this->markup;
    }

    /**
     * Public disk paths of the pictures a page builder layout uses, from its design and HTML.
     *
     * The editor writes each picture as /storage/{path}. Built sizes are left out, since
     * the builder only offers originals.
     *
     * @return array<int, string>
     */
    public static function sources(mixed $design, mixed $markup): array
    {
        $text = (is_array($design) ? json_encode(self::project($design), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '').' '.(string) $markup;

        preg_match_all('~/storage/([^"\'\s)\\\\?#<>]+)~u', $text, $found);

        return array_values(array_unique(array_filter(
            array_map('rawurldecode', $found[1]),
            fn (string $path): bool => ! str_starts_with($path, Sizes::ROOT.'/') && ! str_contains($path, '..'),
        )));
    }

    /**
     * The design and HTML with every picture component that shows one path taken out.
     *
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    public static function erase(mixed $design, ?string $markup, string $path): array
    {
        $address = Library::url($path);

        if (is_array($design)) {
            $design = self::prune($design, $address);
        }

        if ($markup !== null) {
            $markup = (string) preg_replace('~<img\b[^>]*\bsrc=["\']'.preg_quote($address, '~').'["\'][^>]*>~iu', '', $markup);
        }

        return [is_array($design) ? $design : null, $markup];
    }

    /**
     * The design without the asset list GrapesJS keeps, which lists every offered picture and not only the used ones.
     *
     * @param  array<string, mixed>  $design
     * @return array<string, mixed>
     */
    public static function project(array $design): array
    {
        unset($design['assets']);

        return $design;
    }

    /**
     * One design node and everything inside it, without components whose picture is the given address.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function prune(array $node, string $address): array
    {
        foreach ($node as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (array_is_list($value)) {
                $node[$key] = array_values(array_filter(
                    array_map(fn (mixed $item): mixed => is_array($item) ? self::prune($item, $address) : $item, $value),
                    fn (mixed $item): bool => ! is_array($item) || (($item['src'] ?? $item['attributes']['src'] ?? null) !== $address),
                ));
            } else {
                $node[$key] = self::prune($value, $address);
            }
        }

        return $node;
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
            'commentable' => 'boolean',
            'position' => 'integer',
            'design' => 'array',
            'schemas' => 'array',
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
