<?php

namespace App\Policies;

use App\Auth\Section;
use App\Models\Article;
use App\Models\User;

/**
 * Who may open the articles section of the panel.
 *
 * One permission covers the whole section. The owner passes through Gate::before.
 *
 * Extending:
 * - Keep these method names. Filament calls them.
 * - A finer permission belongs in App\Auth\Section.
 */
class ArticlePolicy
{
    /**
     * Whether the actor may see the articles list.
     */
    public function viewAny(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may open one article.
     */
    public function view(User $actor, Article $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may create an article.
     */
    public function create(User $actor): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may edit an article.
     */
    public function update(User $actor, Article $record): bool
    {
        return $this->allow($actor);
    }

    /**
     * Whether the actor may delete an article.
     */
    public function delete(User $actor, Article $record): bool
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
