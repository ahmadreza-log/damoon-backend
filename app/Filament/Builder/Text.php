<?php

namespace App\Filament\Builder;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\TextInput;

/**
 * A block of formatted text with an optional heading.
 *
 * The editor stores HTML in data.text. Only staff who may edit pages write it.
 *
 * Extending:
 * - The view is resources/views/blocks/text.blade.php.
 */
class Text extends Block
{
    protected const DOCUMENTS = ['text'];

    /**
     * The Persian name of the block.
     */
    public static function label(): string
    {
        return 'متن';
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
     * Heading and text fields.
     *
     * @return array<int, mixed>
     */
    public static function getBlockSchema(): array
    {
        return [
            TextInput::make('heading')->label('تیتر')->maxLength(255),
            RichEditor::make('text')
                ->label('متن')
                ->required()
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['h2', 'h3', 'paragraph'],
                    ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
                    ['blockquote', 'bulletList', 'orderedList', 'table'],
                    ['undo', 'redo'],
                ]),
        ];
    }

    /**
     * The text as HTML.
     *
     * Saved data holds HTML, while the live preview can hold the editor's JSON document, so both are accepted.
     */
    public static function html(mixed $text): string
    {
        if (! is_string($text) && ! is_array($text)) {
            return '';
        }

        return RichContentRenderer::make($text)->toUnsafeHtml();
    }
}
