<?php

namespace App\Policies;

use App\Auth\Section;
use App\Models\Project;
use App\Models\User;

/**
 * Who may open the projects section of the panel.
 *
 * Projects have their own permission, separate from the other content. The owner passes through Gate::before.
 *
 * Extending:
 * - Keep these method names. Filament calls them.
 * - A finer permission belongs in App\Auth\Section.
 */
class ProjectPolicy
{
    /**
     * Whether the actor may see the projects list.
     */
    public function viewAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may open one project.
     */
    public function view(User $actor, Project $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may create a project.
     */
    public function create(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may edit a project.
     */
    public function update(User $actor, Project $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may delete a project.
     */
    public function delete(User $actor, Project $record): bool
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
     * The projects section permission.
     */
    private function allow(User $actor): bool
    {
        return $actor->can(Section::PROJECTS);
    }
}
