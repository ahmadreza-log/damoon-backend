<?php

namespace App\Policies;

use App\Auth\Section;
use App\Models\Customer;
use App\Models\User;

/**
 * Who may open the customers section of the panel.
 *
 * One permission covers the whole section. The owner passes through Gate::before.
 *
 * Extending:
 * - Keep these method names. Filament calls them.
 * - A finer permission belongs in App\Auth\Section.
 */
class CustomerPolicy
{
    /**
     * Whether the actor may see the customers list.
     */
    public function viewAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may open one customer.
     */
    public function view(User $actor, Customer $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may create a customer.
     */
    public function create(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may edit a customer.
     */
    public function update(User $actor, Customer $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may delete a customer.
     */
    public function delete(User $actor, Customer $record): bool
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
     * The customers section permission.
     */
    private function allow(User $actor): bool
    {
        return $actor->can(Section::CUSTOMERS);
    }
}
