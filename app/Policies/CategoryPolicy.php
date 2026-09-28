<?php

namespace App\Policies;

use App\Auth\Section;
use App\Models\Category;
use App\Models\User;

/**
 * Who may manage article categories.
 *
 * Categories sit under the articles section, so the same permission covers them.
 * The owner passes through Gate::before.
 *
 * Extending:
 * - Keep these method names. Filament calls them.
 * - A separate categories permission belongs in App\Auth\Section.
 */
class CategoryPolicy
{
    /**
     * Whether the actor may see the categories list.
     */
    public function viewAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may open one category.
     */
    public function view(User $actor, Category $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may create a category.
     */
    public function create(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may edit a category.
     */
    public function update(User $actor, Category $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may delete a category.
     */
    public function delete(User $actor, Category $record): bool
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
