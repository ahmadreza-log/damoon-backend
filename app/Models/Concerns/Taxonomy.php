<?php

namespace App\Models\Concerns;

use App\Models\Article;
use App\Support\Sizes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shared behaviour for article groups such as categories and tags.
 *
 * The model needs slug, parent_id, and banners columns and a FOLDER constant
 * for banner images. The slug is filled from the name when left blank, and banner
 * images get their sizes from Sizes. Banners are media library files, so they
 * stay in the library when the record is deleted.
 *
 * Extending:
 * - Cast banners to array on the model.
 * - Eloquent calls bootTaxonomy by name.
 */
trait Taxonomy
{
    /**
     * Fills the slug and builds sizes for new banner images.
     */
    protected static function bootTaxonomy(): void
    {
        static::saving(function (self $record): void {
            $record->place();
        });

        static::saved(function (self $record): void {
            $record->resize();
        });
    }

    /**
     * The record this one sits under.
     *
     * @return BelongsTo<static, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * Records directly under this one.
     *
     * @return HasMany<static, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * This record's id and the ids of every record under it, at any depth.
     *
     * The parent field hides these so a record cannot sit under itself.
     *
     * @return array<int, int>
     */
    public function family(): array
    {
        $ids = [(int) $this->getKey()];
        $level = $ids;

        while ($level !== []) {
            $level = static::query()
                ->whereIn('parent_id', $level)
                ->whereNotIn('id', $ids)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            $ids = [...$ids, ...$level];
        }

        return $ids;
    }

    /**
     * The name with every parent before it, for example فناوری › هوش مصنوعی.
     *
     * The article form shows this in its category and tag lists.
     */
    public function trail(): string
    {
        $names = [(string) $this->name];
        $seen = [(int) $this->getKey()];
        $parent = $this->parent;

        while ($parent !== null && ! in_array((int) $parent->getKey(), $seen, true)) {
            array_unshift($names, (string) $parent->name);
            $seen[] = (int) $parent->getKey();
            $parent = $parent->parent;
        }

        return implode(' › ', $names);
    }

    /**
     * Banner image paths on the public disk.
     *
     * @return array<int, string>
     */
    public static function pictures(mixed $banners): array
    {
        $paths = [];

        foreach ((array) $banners as $banner) {
            $image = is_array($banner) ? ($banner['image'] ?? null) : null;

            if (is_string($image) && $image !== '') {
                $paths[] = $image;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Stores a unique slug, using the name when the field is blank.
     */
    private function place(): void
    {
        $source = Article::link((string) ($this->slug !== null && $this->slug !== '' ? $this->slug : $this->name));
        $base = $source !== '' ? $source : strtolower(class_basename(static::class));
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
     * Builds sizes for banner images that were not on the record before this save.
     *
     * Runs in saved, while getOriginal still holds the previous values.
     */
    private function resize(): void
    {
        foreach (array_diff(self::pictures($this->banners), self::pictures($this->getOriginal('banners'))) as $path) {
            Sizes::ensure($path);
        }
    }
}
