<?php

namespace App\Models;

/**
 * The two genders a staff profile can store.
 *
 * The database keeps the English key. The panel shows the Persian label.
 *
 * Extending:
 * - Add a constant and a label in options. The form and the column both read options.
 */
class Gender
{
    /** A woman. The panel label is خانم. */
    public const WOMAN = 'woman';

    /** A man. The panel label is آقا. */
    public const MAN = 'man';

    /**
     * Persian labels for the gender field.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::WOMAN => 'خانم',
            self::MAN => 'آقا',
        ];
    }
}
