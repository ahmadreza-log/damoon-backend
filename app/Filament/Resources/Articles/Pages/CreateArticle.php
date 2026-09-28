<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creates an article. An empty slug is filled from the title on the model.
 *
 * Extending:
 * - Filament owns the mutateFormDataBeforeCreate method name.
 */
class CreateArticle extends CreateRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = ArticleResource::class;

    /**
     * Sets the author to the signed-in user when the field was left empty.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['author_id'] ?? null)) {
            $user = Filament::auth()->user();
            $data['author_id'] = $user instanceof User ? $user->getKey() : null;
        }

        return $data;
    }
}
