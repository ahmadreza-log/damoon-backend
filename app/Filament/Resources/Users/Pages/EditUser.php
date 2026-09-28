<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits a user and the panel sections that user may open.
 *
 * The checklist is not a database column. It is stored as Spatie permissions
 * after the account fields are saved. The owner always keeps every section.
 *
 * Extending:
 * - Filament owns the mutate and afterSave method names.
 * - Add a section in App\Auth\Section. This page already saves that list.
 */
class EditUser extends EditRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = UserResource::class;

    /**
     * Section permission names taken off the form before the user row is saved.
     *
     * @var array<int, mixed>
     */
    protected array $chosen = [];

    /**
     * Fills the checklist from the user's current sections.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if ($record instanceof User) {
            $data['sections'] = $record->sections();
        }

        return $data;
    }

    /**
     * Keeps the checklist out of the users table update.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->chosen = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        unset($data['sections']);

        return $data;
    }

    /**
     * Writes the chosen sections after the account fields are stored.
     */
    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof User) {
            $record->grant($this->chosen);
        }
    }
}
