<?php

namespace App\Filament\Builder;

use App\Filament\Fields\MediaPicker;
use Filament\Forms\Components\TextInput;

/**
 * One picture from the media library, with a caption and an optional link.
 *
 * Extending:
 * - The view is resources/views/blocks/image.blade.php.
 */
class Image extends Block
{
    /** The picture. */
    protected const IMAGES = ['image'];

    /**
     * The Persian name of the block.
     */
    public static function label(): string
    {
        return 'تصویر';
    }

    /**
     * The group in the block list.
     */
    public static function group(): string
    {
        return 'رسانه';
    }

    /**
     * The caption is shown on the block in the page builder list.
     */
    public static function getBlockTitleAttribute(): ?string
    {
        return 'caption';
    }

    /**
     * Picture, caption, and link fields.
     *
     * @return array<int, mixed>
     */
    public static function getBlockSchema(): array
    {
        return [
            MediaPicker::make('image')->label('تصویر')->required()->directory(self::FOLDER),
            TextInput::make('caption')->label('توضیح تصویر')->maxLength(255),
            self::link('لینک'),
        ];
    }
}
