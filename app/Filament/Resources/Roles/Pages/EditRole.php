<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Resources\Pages\EditRecord;

/**
 * Edits a role and the panel sections that role may open.
 *
 * The checklist is not a database column. It is stored as Spatie permissions
 * after the name and key are saved. Developer and owner always keep every section.
 *
 * Extending:
 * - Filament owns the mutate and afterSave method names.
 * - Add a section in App\Auth\Section. This page already saves that list.
 */
class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * Section permission names taken off the form before the role row is saved.
     *
     * @var array<int, mixed>
     */
    protected array $chosen = [];

    /**
     * Fills the checklist from the role's current sections.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if ($record instanceof Role) {
            $data['sections'] = $record->sections();
        }

        return $data;
    }

    /**
     * Keeps the checklist out of the roles table update.
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
     * Writes the chosen sections after the name and key are stored.
     */
    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Role) {
            $record->grant($this->chosen);
        }
    }
}
