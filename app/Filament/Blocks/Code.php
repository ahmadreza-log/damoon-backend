<?php

namespace App\Filament\Blocks;

use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * A rich editor block that holds hand-written HTML, CSS, or JavaScript.
 *
 * In the stored JSON it is a customBlock node with id code and a config of
 * language and code. On the site the code is written into the page as it is:
 * HTML directly, CSS inside a style tag, and JavaScript inside a script tag.
 * Only staff who may edit articles can add it, so the code is trusted.
 *
 * Extending:
 * - Filament owns every method name here.
 * - A new language belongs in LANGUAGES, in toHtml(), and in Language when the editor should colour it.
 */
class Code extends RichContentCustomBlock
{
    /**
     * Languages the block accepts, keyed by the value stored in config.language.
     *
     * The keys match Filament's CodeEditor Language enum so the editor can colour the code.
     * The values are the names shown in the language select and the preview label.
     *
     * @var array<string, string>
     */
    public const LANGUAGES = [
        'html' => 'HTML',
        'css' => 'CSS',
        'javascript' => 'JavaScript',
    ];

    /**
     * The id written into the stored customBlock node.
     *
     * Saved articles point at the block by this id, so changing it orphans every existing code block.
     */
    public static function getId(): string
    {
        return 'code';
    }

    /**
     * The name shown in the editor's custom blocks menu.
     */
    public static function getLabel(): string
    {
        return 'کد دلخواه';
    }

    /**
     * The icon shown next to the label in the custom blocks menu.
     */
    public static function getIcon(): Heroicon
    {
        return Heroicon::OutlinedCodeBracket;
    }

    /**
     * The heading shown above the block inside the editor, including the chosen language.
     *
     * An unknown or missing language falls back to HTML, which is also what toHtml() does.
     *
     * @param  array<string, mixed>  $config
     */
    public static function getPreviewLabel(array $config): string
    {
        return 'کد دلخواه ('.(self::LANGUAGES[$config['language'] ?? 'html'] ?? 'HTML').')';
    }

    /**
     * The code shown read-only inside the editor.
     *
     * @param  array<string, mixed>  $config
     */
    public static function toPreviewHtml(array $config): string
    {
        $code = e((string) ($config['code'] ?? ''));

        return '<pre dir="ltr" style="text-align:left;max-height:14rem;overflow:auto;margin:0;padding:0.75rem;border-radius:0.5rem;background:rgba(0,0,0,0.25);font-size:0.8rem"><code>'.$code.'</code></pre>';
    }

    /**
     * The code as it goes into the page.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data
     */
    public static function toHtml(array $config, array $data): string
    {
        $code = (string) ($config['code'] ?? '');

        return match ($config['language'] ?? 'html') {
            'css' => '<style>'.$code.'</style>',
            'javascript' => '<script>'.$code.'</script>',
            default => $code,
        };
    }

    /**
     * The popup that inserts or edits the block.
     *
     * It asks for the language first; the select is live so the code editor below
     * switches its colouring as soon as the language changes. The code field stays
     * left-to-right inside the RTL panel because code is always written in Latin script.
     */
    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalHeading('کد دلخواه')
            ->modalDescription('این کد همان‌طور که نوشته شود در صفحهٔ سایت قرار می‌گیرد.')
            ->modalSubmitActionLabel('درج')
            ->schema([
                Select::make('language')
                    ->label('نوع کد')
                    ->options(self::LANGUAGES)
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
            ]);
    }
}
