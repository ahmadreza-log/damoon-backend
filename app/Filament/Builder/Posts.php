<?php

namespace App\Filament\Builder;

use App\Models\Article;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The newest articles, optionally from one category.
 *
 * The list is read when the page is drawn, so a new article shows up without editing the page.
 * Articles with a publish date in the future are left out.
 *
 * Extending:
 * - The view is resources/views/blocks/posts.blade.php.
 */
class Posts extends Block
{
    /** The largest number of articles the block may show. */
    public const LIMIT = 12;

    /**
     * The Persian name of the block.
     */
    public static function label(): string
    {
        return 'آخرین نوشته‌ها';
    }

    /**
     * The group in the block list.
     */
    public static function group(): string
    {
        return 'متن';
    }

    /**
     * The heading is shown on the block in the page builder list.
     */
    public static function getBlockTitleAttribute(): ?string
    {
        return 'heading';
    }

    /**
     * Heading, count, and category fields.
     *
     * @return array<int, mixed>
     */
    public static function getBlockSchema(): array
    {
        return [
            TextInput::make('heading')->label('تیتر')->maxLength(255),
            TextInput::make('count')->label('تعداد')->integer()->minValue(1)->maxValue(self::LIMIT)->default(3)->required(),
            Select::make('category')
                ->label('دسته‌بندی')
                ->options(fn (): array => Category::query()->with('parent')->get()->mapWithKeys(fn (Category $category): array => [$category->getKey() => $category->trail()])->all())
                ->searchable()
                ->native(false)
                ->placeholder('همهٔ دسته‌ها'),
        ];
    }

    /**
     * The articles one block shows, newest first.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, Article>
     */
    public static function articles(array $data): Collection
    {
        $count = max(1, min(self::LIMIT, (int) ($data['count'] ?? 3)));
        $category = $data['category'] ?? null;

        return Article::query()
            ->where('published_at', '<=', now())
            ->when(filled($category), fn (Builder $query): Builder => $query->whereHas('categories', fn (Builder $inner): Builder => $inner->whereKey($category)))
            ->latest('published_at')
            ->limit($count)
            ->get(['id', 'title', 'slug', 'cover', 'published_at']);
    }
}
