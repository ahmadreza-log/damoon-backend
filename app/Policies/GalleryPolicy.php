<?php

namespace App\Policies;

use App\Auth\Section;
use App\Models\Gallery;
use App\Models\User;

/**
 * Who may open the galleries section of the panel.
 *
 * Galleries have their own permission, separate from the other content. The owner passes through Gate::before.
 *
 * Extending:
 * - Keep these method names. Filament calls them.
 * - A finer permission belongs in App\Auth\Section.
 */
class GalleryPolicy
{
    /**
     * Whether the actor may see the galleries list.
     */
    public function viewAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may open one gallery.
     */
    public function view(User $actor, Gallery $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may create a gallery.
     */
    public function create(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may edit a gallery.
     */
    public function update(User $actor, Gallery $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may delete a gallery.
     */
    public function delete(User $actor, Gallery $record): bool
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
     * The galleries section permission.
     */
    private function allow(User $actor): bool
    {
        return $actor->can(Section::GALLERIES);
    }
}
