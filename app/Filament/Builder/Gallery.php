<?php

namespace App\Filament\Builder;

use App\Filament\Fields\MediaPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * A grid of pictures from the media library.
 *
 * Extending:
 * - The view is resources/views/blocks/gallery.blade.php.
 * - A new column count belongs in COLUMNS.
 */
class Gallery extends Block
{
    /** The pictures, in the order they were arranged. */
    protected const IMAGES = ['images'];

    /**
     * Column counts the gallery offers, with their Persian names.
     *
     * @var array<int, string>
     */
    public const COLUMNS = [2 => 'دو ستون', 3 => 'سه ستون', 4 => 'چهار ستون'];

    /**
     * The Persian name of the block.
     */
    public static function label(): string
    {
        return 'گالری';
    }

    /**
     * The group in the block list.
     */
    public static function group(): string
    {
        return 'رسانه';
    }

    /**
     * The heading is shown on the block in the page builder list.
     */
    public static function getBlockTitleAttribute(): ?string
    {
        return 'heading';
    }

    /**
     * Heading, pictures, and column count fields.
     *
     * @return array<int, mixed>
     */
    public static function getBlockSchema(): array
    {
        return [
            TextInput::make('heading')->label('تیتر')->maxLength(255),
            MediaPicker::make('images')
                ->label('تصاویر')
                ->multiple()
                ->required()
                ->directory(self::FOLDER)
                ->helperText('ترتیب تصاویر را با کشیدن عوض کنید.'),
            Select::make('columns')
                ->label('تعداد ستون')
                ->options(self::COLUMNS)
                ->default(3)
                ->required()
                ->native(false)
                ->selectablePlaceholder(false),
        ];
    }
}
