<?php

namespace App\Filament\Builder;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * A list of questions that open to show their answers.
 *
 * Extending:
 * - The view is resources/views/blocks/faq.blade.php.
 */
class Faq extends Block
{
    /**
     * The Persian name of the block.
     */
    public static function label(): string
    {
        return 'سوالات متداول';
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
     * Heading and question list fields.
     *
     * @return array<int, mixed>
     */
    public static function getBlockSchema(): array
    {
        return [
            TextInput::make('heading')->label('تیتر')->maxLength(255),
            Repeater::make('items')
                ->label('سوالات')
                ->schema([
                    TextInput::make('question')->label('سوال')->required()->maxLength(255),
                    Textarea::make('answer')->label('پاسخ')->required()->rows(3),
                ])
                ->minItems(1)
                ->defaultItems(1)
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                ->addActionLabel('افزودن سوال'),
        ];
    }
}
