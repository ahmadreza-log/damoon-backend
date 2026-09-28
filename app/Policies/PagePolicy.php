<?php

namespace App\Policies;

use App\Auth\Section;
use App\Models\Page;
use App\Models\User;

/**
 * Who may open the pages section of the panel.
 *
 * Pages have their own permission, separate from articles, so a role can manage
 * site pages without the blog or the other way round. The owner passes through Gate::before.
 *
 * Extending:
 * - Keep these method names. Filament calls them.
 * - A finer permission belongs in App\Auth\Section.
 */
class PagePolicy
{
    /**
     * Whether the actor may see the pages list.
     */
    public function viewAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may open one page.
     */
    public function view(User $actor, Page $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may create a page.
     */
    public function create(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may edit a page.
     */
    public function update(User $actor, Page $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may delete a page.
     */
    public function delete(User $actor, Page $record): bool
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
     * The pages section permission.
     */
    private function allow(User $actor): bool
    {
        return $actor->can(Section::PAGES);
    }
}
