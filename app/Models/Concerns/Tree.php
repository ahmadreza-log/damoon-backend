<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A parent and children on the same table, for records that nest like folders.
 *
 * Categories, tags, and pages use it. The model needs a nullable parent_id column
 * pointing at its own table, and caption, the text one level of the trail shows.
 *
 * Extending:
 * - Hide family from the parent field so a record cannot sit under itself.
 * - trail stops at a record it has already seen, so a broken loop in old data cannot hang it.
 *
 * @mixin Model
 *
 * @property int|null $parent_id
 * @property-read static|null $parent
 */
trait Tree
{
    /**
     * The text this record shows in a trail, such as its name or title.
     */
    abstract public function caption(): string;

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
     * The caption with every parent before it, for example فناوری › هوش مصنوعی.
     *
     * The article form shows this in its category and tag lists, and the page form in its parent list.
     */
    public function trail(): string
    {
        $names = [$this->caption()];
        $seen = [(int) $this->getKey()];
        $parent = $this->parent;

        while ($parent !== null && ! in_array((int) $parent->getKey(), $seen, true)) {
            array_unshift($names, $parent->caption());
            $seen[] = (int) $parent->getKey();
            $parent = $parent->parent;
        }

        return implode(' › ', $names);
    }
}
