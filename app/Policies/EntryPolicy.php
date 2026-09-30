<?php

namespace App\Policies;

use App\Auth\Section;
use App\Models\Entry;
use App\Models\User;

/**
 * Who may open the inbox of form messages.
 *
 * Messages come from the site, so nobody creates one in the panel. Reading, changing the
 * status, and deleting need the inbox permission. The owner passes through Gate::before.
 *
 * Extending:
 * - Keep these method names. Filament calls them.
 */
class EntryPolicy
{
    /**
     * Whether the actor may see the inbox.
     */
    public function viewAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may open one message.
     */
    public function view(User $actor, Entry $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Messages are only written by the site.
     */
    public function create(User $actor): bool
    {
        return false;
    }

    /**
     * Whether the actor may mark a message read, unread, or archived.
     */
    public function update(User $actor, Entry $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may delete a message.
     */
    public function delete(User $actor, Entry $record): bool
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
     * The inbox section permission.
     */
    private function allow(User $actor): bool
    {
        return $actor->can(Section::INBOX);
    }
}
