<?php

namespace App\Filament\Builder;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * A heading over a row of short items, each with a title and a line of text.
 *
 * Extending:
 * - The view is resources/views/blocks/features.blade.php.
 */
class Features extends Block
{
    /**
     * The Persian name of the block.
     */
    public static function label(): string
    {
        return 'ویژگی‌ها';
    }

    /**
     * The group in the block list.
     */
    public static function group(): string
    {
        return 'بخش‌های صفحه';
    }

    /**
     * The heading is shown on the block in the page builder list.
     */
    public static function getBlockTitleAttribute(): ?string
    {
        return 'heading';
    }

    /**
     * Heading and item list fields.
     *
     * @return array<int, mixed>
     */
    public static function getBlockSchema(): array
    {
        return [
            TextInput::make('heading')->label('تیتر')->maxLength(255),
            Repeater::make('items')
                ->label('موارد')
                ->schema([
                    TextInput::make('title')->label('عنوان')->required()->maxLength(255),
                    Textarea::make('text')->label('توضیح')->rows(2)->maxLength(500),
                ])
                ->minItems(1)
                ->defaultItems(3)
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->addActionLabel('افزودن مورد'),
        ];
    }
}
