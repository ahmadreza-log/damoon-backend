<?php

namespace App\Models;

use App\Support\Frontend;
use App\Support\Sizes;
use Database\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A gallery (گالری) in the content group: a titled, ordered list of media library files of one kind.
 *
 * kind says whether it holds pictures, videos, or audio files (Kind). items is the list of
 * public disk paths in the order staff dragged them; every file comes from the media library,
 * and new uploads land in folder(kind). The files stay in the library when the gallery is
 * deleted, and deleting a file from the library takes it out of the gallery (Library::drop).
 * The slug is filled from the title when the form leaves it blank.
 *
 * Extending:
 * - Add a column in a galleries migration, Fillable, and GalleryResource together.
 * - Another kind of gallery is one more case on Kind.
 */
#[Fillable([
    'title',
    'slug',
    'kind',
    'description',
    'items',
])]
class Gallery extends Model
{
    /** @use HasFactory<GalleryFactory> */
    use HasFactory;

    /** The public folder new gallery uploads go under, one subfolder per kind. */
    public const FOLDER = 'galleries';

    /**
     * Fills the slug and builds sizes for new pictures.
     *
     * Eloquent owns this method name.
     */
    protected static function booted(): void
    {
        static::saving(function (Gallery $gallery): void {
            $gallery->place();
        });

        static::saved(function (Gallery $gallery): void {
            $gallery->resize();
        });
    }

    /**
     * The public folder uploads of one kind go to, such as galleries/video.
     */
    public static function folder(Kind $kind): string
    {
        return self::FOLDER.'/'.$kind->value;
    }

    /**
     * The chosen file paths in order, skipping empty items.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        return array_values(array_filter((array) $this->items, fn (mixed $path): bool => is_string($path) && $path !== ''));
    }

    /**
     * The gallery's address on the public website.
     */
    public function link(): string
    {
        return Frontend::link($this);
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
            'kind' => Kind::class,
            'items' => 'array',
        ];
    }

    /**
     * Stores a unique slug, using the title when the field is blank.
     */
    private function place(): void
    {
        $source = Article::link((string) ($this->slug !== null && $this->slug !== '' ? $this->slug : $this->title));
        $base = $source !== '' ? $source : 'gallery';
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
     * Builds sizes for the pictures that were not in the gallery before this save.
     *
     * Runs in saved, while getOriginal still holds the previous list.
     */
    private function resize(): void
    {
        if ($this->kind !== Kind::Image) {
            return;
        }

        $before = (array) json_decode((string) $this->getRawOriginal('items'), true);

        foreach (array_diff($this->paths(), $before) as $path) {
            Sizes::ensure($path);
        }
    }
}
