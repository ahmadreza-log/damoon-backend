<?php

namespace App\Policies;

use App\Auth\Section;
use App\Models\User;

/**
 * Who may open the users section of the panel.
 *
 * One permission covers the whole section. The owner passes through Gate::before
 * and still cannot be deleted, because the resource and the model both refuse it.
 *
 * Extending:
 * - Keep these method names. Filament calls them.
 * - A finer permission belongs in App\Auth\Section, not as a second policy for the same model.
 */
class UserPolicy
{
    /**
     * Whether the actor may see the users list.
     */
    public function viewAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may open one user.
     */
    public function view(User $actor, User $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may create a user.
     */
    public function create(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may edit a user, including that user's sections.
     */
    public function update(User $actor, User $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may delete a user. The owner account is never deleted.
     */
    public function delete(User $actor, User $record): bool
    {
        return $this->allow($actor) && ! $record->owner();
    }

    /**
     * Whether the bulk delete action is available.
     *
     * Each row is still refused by the model when the row is the owner.
     */
    public function deleteAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * The users section permission.
     */
    private function allow(User $actor): bool
    {
        return $actor->can(Section::USERS);
    }
}
