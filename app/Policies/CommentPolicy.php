<?php

namespace App\Policies;

use App\Auth\Section;
use App\Models\Comment;
use App\Models\User;

/**
 * Who may moderate comments in the panel.
 *
 * Comments have their own permission, so a role can moderate them without editing
 * articles or pages. Comments come from the site, so nobody creates one from the panel;
 * staff answer an existing comment instead. The owner passes through Gate::before.
 *
 * Extending:
 * - Keep these method names. Filament calls them.
 * - A finer permission belongs in App\Auth\Section.
 */
class CommentPolicy
{
    /**
     * Whether the actor may see the comments list.
     */
    public function viewAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may open one comment.
     */
    public function view(User $actor, Comment $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Comments are written on the site, not in the panel.
     */
    public function create(User $actor): bool
    {
        return false;
    }

    /**
     * Whether the actor may edit, approve, or answer a comment.
     */
    public function update(User $actor, Comment $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may delete a comment.
     */
    public function delete(User $actor, Comment $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the bulk actions are available.
     */
    public function deleteAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * The comments section permission.
     */
    private function allow(User $actor): bool
    {
        return $actor->can(Section::COMMENTS);
    }
}
