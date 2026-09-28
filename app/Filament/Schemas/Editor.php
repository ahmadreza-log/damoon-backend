<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;
use Filament\Support\Icons\Heroicon;

/**
 * The rich body editor shared by the article and page forms.
 *
 * It saves Tiptap JSON into the content column, shows every Filament tool except
 * merge tags, and stores attached pictures on the public disk so they appear in
 * the media library. The model behind the form reads the same JSON through Body.
 *
 * Extending:
 * - A new toolbar button goes in toolbarButtons here, so both forms get it.
 * - Pass the model's FOLDER and BLOCKS, so the renderer and the media library agree with the editor.
 */
class Editor
{
    /**
     * The content field.
     *
     * @param  string  $folder  Public folder for attached pictures, the model's FOLDER.
     * @param  array<int, class-string>  $blocks  Custom blocks, the model's BLOCKS.
     */
    public static function body(string $folder, array $blocks): RichEditor
    {
        return RichEditor::make('content')
            ->label('محتوا')
            ->json()
            ->customBlocks($blocks)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'code', 'link'],
                ['textColor', 'highlight', 'small', 'lead', 'clearFormatting'],
                [
                    ToolbarButtonGroup::make('تیتر', ['paragraph', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'])
                        ->icon(Heroicon::OutlinedH1)
                        ->textualButtons(),
                ],
                ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'],
                ['blockquote', 'codeBlock', 'bulletList', 'orderedList', 'horizontalRule', 'details'],
                ['table', 'grid', 'gridDelete', 'attachFiles', 'customBlocks'],
                ['undo', 'redo'],
            ])
            ->customTextColors()
            ->resizableImages()
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsDirectory($folder)
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->fileAttachmentsMaxSize(10240)
            ->required()
            ->helperText('برای گذاشتن کد HTML، CSS یا JavaScript از دکمهٔ بلوک‌ها، «کد دلخواه» را به متن بکشید.')
            ->columnSpanFull();
    }
}
