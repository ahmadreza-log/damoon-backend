<?php

namespace App\Policies;

use App\Auth\Section;
use App\Models\Tag;
use App\Models\User;

/**
 * Who may manage article tags.
 *
 * Tags sit under the articles section, so the same permission covers them.
 * The owner passes through Gate::before.
 *
 * Extending:
 * - Keep these method names. Filament calls them.
 * - A separate tags permission belongs in App\Auth\Section.
 */
class TagPolicy
{
    /**
     * Whether the actor may see the tags list.
     */
    public function viewAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may open one tag.
     */
    public function view(User $actor, Tag $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may create a tag.
     */
    public function create(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may edit a tag.
     */
    public function update(User $actor, Tag $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may delete a tag.
     */
    public function delete(User $actor, Tag $record): bool
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
     * The articles section permission.
     */
    private function allow(User $actor): bool
    {
        return $actor->can(Section::ARTICLES);
    }
}
