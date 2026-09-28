<?php

namespace App\Http\Resources\V1;

/**
 * The questions repeater of an article, category, or tag as the content API sends it.
 *
 * Rows without a question are left out, so a half-filled row never reaches the site.
 *
 * Extending:
 * - A new repeater field is one more key in each row.
 */
final class Questions
{
    /**
     * @return array<int, array{question: string, answer: string}>
     */
    public static function make(mixed $rows): array
    {
        $list = [];

        foreach ((array) $rows as $row) {
            $question = is_array($row) ? ($row['question'] ?? null) : null;

            if (! is_string($question) || $question === '') {
                continue;
            }

            $list[] = [
                'question' => $question,
                'answer' => is_string($row['answer'] ?? null) ? $row['answer'] : '',
            ];
        }

        return $list;
    }
}
