<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Resources\Pages\CreateRecord;

/**
 * Creates a role and the panel sections that role may open.
 *
 * The checklist is not a database column. It is stored as Spatie permissions
 * after the name and key are saved.
 *
 * Extending:
 * - Filament owns the mutate and afterCreate method names.
 * - Add a section in App\Auth\Section. This page already saves that list.
 */
class CreateRole extends CreateRecord
{
    /** The resource whose form, model, and labels this page uses. */
    protected static string $resource = RoleResource::class;

    /**
     * Section permission names taken off the form before the role row is saved.
     *
     * @var array<int, mixed>
     */
    protected array $chosen = [];

    /**
     * Keeps the checklist out of the roles table insert.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->chosen = is_array($data['sections'] ?? null) ? $data['sections'] : [];
        unset($data['sections']);

        return $data;
    }

    /**
     * Writes the chosen sections after the name and key are stored.
     */
    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Role) {
            $record->grant($this->chosen);
        }
    }
}
