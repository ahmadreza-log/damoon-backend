<?php

namespace App\Http\Resources\V1;

/**
 * The features repeater of a brand as the content API sends it.
 *
 * Rows without a title are left out, so a half-filled row never reaches the site.
 *
 * Extending:
 * - A new repeater field is one more key in each row.
 */
final class Features
{
    /**
     * @return array<int, array{title: string, description: string}>
     */
    public static function make(mixed $rows): array
    {
        $list = [];

        foreach ((array) $rows as $row) {
            $title = is_array($row) ? ($row['title'] ?? null) : null;

            if (! is_string($title) || $title === '') {
                continue;
            }

            $list[] = [
                'title' => $title,
                'description' => is_string($row['description'] ?? null) ? $row['description'] : '',
            ];
        }

        return $list;
    }
}
