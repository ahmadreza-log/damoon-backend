<?php

namespace App\Policies;

use App\Auth\Section;
use App\Models\Brand;
use App\Models\User;

/**
 * Who may open the brands section of the panel.
 *
 * Brands have their own permission, separate from articles and pages. The owner passes through Gate::before.
 *
 * Extending:
 * - Keep these method names. Filament calls them.
 * - A finer permission belongs in App\Auth\Section.
 */
class BrandPolicy
{
    /**
     * Whether the actor may see the brands list.
     */
    public function viewAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may open one brand.
     */
    public function view(User $actor, Brand $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may create a brand.
     */
    public function create(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may edit a brand.
     */
    public function update(User $actor, Brand $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may delete a brand.
     */
    public function delete(User $actor, Brand $record): bool
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
     * The brands section permission.
     */
    private function allow(User $actor): bool
    {
        return $actor->can(Section::BRANDS);
    }
}
