<?php

namespace App\Filament\Builder;

use App\Filament\Blocks\Code as Snippet;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Hand-written HTML, CSS, or JavaScript placed in the page as it is.
 *
 * It uses the same languages and output as the body editor's code block, so a
 * snippet behaves the same in a page body and in the page builder. Only staff who
 * may edit pages can add it, so the code is trusted.
 *
 * Extending:
 * - The view is resources/views/blocks/code.blade.php.
 * - A new language belongs in the body editor's Code block, which this block reads.
 */
class Code extends Block
{
    /**
     * The Persian name of the block.
     */
    public static function label(): string
    {
        return 'کد دلخواه';
    }

    /**
     * The group in the block list.
     */
    public static function group(): string
    {
        return 'متن';
    }

    /**
     * Language and code fields. The code editor colours the chosen language.
     *
     * @return array<int, mixed>
     */
    public static function getBlockSchema(): array
    {
        return [
            Select::make('language')
                ->label('نوع کد')
                ->options(Snippet::LANGUAGES)
                ->default('html')
                ->required()
                ->native(false)
                ->selectablePlaceholder(false)
                ->live(),
            CodeEditor::make('code')
                ->label('کد')
                ->required()
                ->extraAttributes(['dir' => 'ltr', 'style' => 'text-align:left'])
                ->language(fn (Get $get): Language => Language::tryFrom((string) $get('language')) ?? Language::Html),
        ];
    }

    /**
     * The code as it goes into the page: HTML as it is, CSS in a style tag, JavaScript in a script tag.
     *
     * @param  array<string, mixed>  $data
     */
    public static function html(array $data): string
    {
        return Snippet::toHtml(['language' => $data['language'] ?? 'html', 'code' => $data['code'] ?? ''], []);
    }
}
