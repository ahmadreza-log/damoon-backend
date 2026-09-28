<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Article index. The create button sits at the top of the page.
 *
 * Filament owns the getHeaderActions method name.
 */
class ListArticles extends ListRecords
{
    /** The resource whose table, model, and labels this page uses. */
    protected static string $resource = ArticleResource::class;

    /**
     * Actions above the list. Currently only create article.
     *
     * @return array<int, CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
