<?php

namespace App\Policies;

use App\Auth\Section;
use App\Models\Role;
use App\Models\User;

/**
 * Who may open the roles section of the panel.
 *
 * One permission covers the whole section. The owner passes through Gate::before.
 * Developer and owner roles cannot be deleted.
 *
 * Extending:
 * - Keep these method names. Filament calls them.
 * - A finer permission belongs in App\Auth\Section.
 */
class RolePolicy
{
    /**
     * Whether the actor may see the roles list.
     */
    public function viewAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may open one role.
     */
    public function view(User $actor, Role $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may create a role.
     */
    public function create(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may edit a role, including its sections.
     */
    public function update(User $actor, Role $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may delete a role. The fixed roles are never deleted.
     */
    public function delete(User $actor, Role $record): bool
    {
        return $this->allow($actor) && ! $record->locked();
    }

    /**
     * Whether the bulk delete action is available.
     *
     * Each fixed row is still refused by the model.
     */
    public function deleteAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * The roles section permission.
     */
    private function allow(User $actor): bool
    {
        return $actor->can(Section::ROLES);
    }
}
