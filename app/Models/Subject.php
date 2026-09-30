<?php

namespace App\Models;

/**
 * Where a comment lives: the first part of /v1/{type}/{slug}/comments.
 */
enum Subject: string
{
    /** Comments on an article (نوشته). */
    case Articles = 'articles';

    /** Comments on a page (برگه). */
    case Pages = 'pages';

    /**
     * The model this path segment points at.
     *
     * Scramble prints the enum and case docblocks in the OpenAPI document, so notes for
     * developers live here instead.
     *
     * Extending:
     * - Another model that takes comments is one more case and one more arm here; it also
     *   needs a comments() relation, a commentable column, and a name in Comment::kinds.
     *   The route constraint and the API docs pick the new case up on their own.
     *
     * @return class-string<Article|Page>
     */
    public function model(): string
    {
        return match ($this) {
            self::Articles => Article::class,
            self::Pages => Page::class,
        };
    }

    /**
     * Every path segment, for the route constraint.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
