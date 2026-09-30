<?php

namespace App\Models;

use App\Filament\Blocks\Code;
use App\Models\Concerns\Body;
use App\Models\Concerns\Meta;
use App\Support\Seo;
use App\Support\Sizes;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A project (پروژه) in the content group: work the company has done for a client.
 *
 * A project has a title, a slug filled from the title when the form leaves it blank, a
 * description stored as Tiptap JSON through Body, the client's brand logo, the year it
 * was done, the services given, the client's industry, how long it took, where it was
 * built, the client's testimonial (employer name, position, text, and a voice message
 * file), and similar projects. The logo and voice file are media library files: they stay
 * in the library when the project is deleted. SEO lives in seo_meta through Meta, with the
 * logo standing in for the cover. commentable says whether visitors may send comments; it
 * is on by default. Comments leave with the project. A project has no publish date, so
 * every project counts as published.
 *
 * Extending:
 * - Add a column in a projects migration, Fillable, and ProjectResource together.
 * - A new column that stores a public path belongs in Library::uses and Library::drop.
 */
#[Fillable([
    'title',
    'slug',
    'content',
    'logo',
    'year',
    'services',
    'industry',
    'duration',
    'location',
    'employer',
    'position',
    'testimony',
    'voice',
    'commentable',
])]
class Project extends Model
{
    use Body;

    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use Meta;

    /**
     * Custom blocks the description editor offers and the renderer understands.
     *
     * @var array<int, class-string>
     */
    public const BLOCKS = [Code::class];

    /** The site path projects live under, used for the SEO address. */
    public const ADDRESS = 'projects';

    /** The public folder for pictures uploaded inside the description editor. */
    public const FOLDER = 'projects/content';

    /** The public folder for logos uploaded from the project form. */
    public const LOGOS = 'projects/logos';

    /** The public folder for testimonial voice messages. */
    public const VOICES = 'projects/voices';

    /** Audio types the voice message upload accepts. */
    public const TYPES = ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/webm'];

    /** Largest voice message upload in kilobytes. */
    public const WEIGHT = 20480;

    /** The earliest and latest year the form accepts, wide enough for solar and Gregorian years. */
    public const EARLIEST = 1300;

    public const LATEST = 2100;

    /**
     * Fills the slug and builds sizes for new pictures.
     *
     * Eloquent owns this method name.
     */
    protected static function booted(): void
    {
        static::saving(function (Project $project): void {
            $project->place();
        });

        static::saved(function (Project $project): void {
            $project->resize();
        });

        static::deleted(function (Project $project): void {
            $project->comments()->delete();
        });
    }

    /**
     * Visitor comments on this project, of every status.
     *
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'subject');
    }

    /**
     * Other projects listed as similar.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function similar(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'project_similar', 'project_id', 'similar_id');
    }

    /**
     * Projects the site may show: all of them, since a project has no publish date.
     *
     * Kept so comments can treat every commentable model the same way.
     *
     * @param  Builder<Project>  $query
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
            'year' => 'integer',
            'services' => 'array',
            'commentable' => 'boolean',
        ];
    }

    /**
     * Stores a unique slug, using the title when the field is blank.
     */
    private function place(): void
    {
        $source = Article::link((string) ($this->slug !== null && $this->slug !== '' ? $this->slug : $this->title));
        $base = $source !== '' ? $source : 'project';
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
     * Builds sizes for the logo and description pictures that were not on the project before this save.
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
