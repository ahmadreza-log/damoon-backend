<?php

namespace App\Policies;

use App\Auth\Section;
use App\Models\ApiKey;
use App\Models\User;

/**
 * Who may manage the API keys on the API settings page.
 *
 * Keys have their own permission, because a key opens the whole public API. The owner
 * passes through Gate::before.
 *
 * Extending:
 * - Keep these method names. Filament calls them.
 * - A finer permission belongs in App\Auth\Section.
 */
class ApiKeyPolicy
{
    /**
     * Whether the actor may see the keys list.
     */
    public function viewAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may open one key.
     */
    public function view(User $actor, ApiKey $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may make a key.
     */
    public function create(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may edit or regenerate a key.
     */
    public function update(User $actor, ApiKey $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may delete a key.
     */
    public function delete(User $actor, ApiKey $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the bulk delete action is available.
     */
    public function deleteAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * The api section permission.
     */
    private function allow(User $actor): bool
    {
        return $actor->can(Section::API);
    }
}
