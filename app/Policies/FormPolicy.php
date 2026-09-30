<?php

namespace App\Policies;

use App\Auth\Section;
use App\Models\Form;
use App\Models\User;

/**
 * Who may open the form builder in the panel.
 *
 * Forms have their own permission, which also opens the forms settings page. The owner
 * passes through Gate::before.
 *
 * Extending:
 * - Keep these method names. Filament calls them.
 * - A finer permission belongs in App\Auth\Section.
 */
class FormPolicy
{
    /**
     * Whether the actor may see the forms list.
     */
    public function viewAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may open one form.
     */
    public function view(User $actor, Form $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may create a form.
     */
    public function create(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may edit a form.
     */
    public function update(User $actor, Form $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may copy a form.
     */
    public function replicate(User $actor, Form $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may delete a form.
     */
    public function delete(User $actor, Form $record): bool
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
     * The forms section permission.
     */
    private function allow(User $actor): bool
    {
        return $actor->can(Section::FORMS);
    }
}
