<?php

namespace App\Filament\Builder;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * A call to action (دعوت به اقدام): a short message with one button.
 *
 * Extending:
 * - The view is resources/views/blocks/callout.blade.php.
 */
class Callout extends Block
{
    /**
     * The Persian name of the block.
     */
    public static function label(): string
    {
        return 'دعوت به اقدام';
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
     * Heading, text, and button fields.
     *
     * @return array<int, mixed>
     */
    public static function getBlockSchema(): array
    {
        return [
            TextInput::make('heading')->label('تیتر')->required()->maxLength(255),
            Textarea::make('text')->label('متن')->rows(2)->maxLength(500),
            TextInput::make('button')->label('متن دکمه')->required()->maxLength(100),
            self::link('لینک دکمه')->required(),
        ];
    }
}
