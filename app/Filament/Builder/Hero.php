<?php

namespace App\Filament\Builder;

use App\Filament\Fields\MediaPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * A large banner at the top of a page: heading, text, background picture, and one button.
 *
 * Extending:
 * - The view is resources/views/blocks/hero.blade.php.
 */
class Hero extends Block
{
    /** The background picture. */
    protected const IMAGES = ['image'];

    /**
     * The Persian name of the block.
     */
    public static function label(): string
    {
        return 'بنر اصلی';
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
     * Heading, text, picture, and button fields.
     *
     * @return array<int, mixed>
     */
    public static function getBlockSchema(): array
    {
        return [
            TextInput::make('heading')->label('تیتر')->required()->maxLength(255),
            Textarea::make('text')->label('متن')->rows(3)->maxLength(1000),
            MediaPicker::make('image')->label('تصویر پس‌زمینه')->directory(self::FOLDER),
            TextInput::make('button')->label('متن دکمه')->maxLength(100),
            self::link('لینک دکمه'),
        ];
    }
}
