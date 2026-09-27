<?php

namespace App\Models;

/**
 * Education levels a staff profile can store.
 *
 * The database keeps the English key. The panel shows the Persian label.
 *
 * Extending:
 * - Add a constant and a label in options. The form reads options.
 */
class Degree
{
    /** No certificate. The panel label is بدون مدرک. */
    public const NONE = 'none';

    /** Middle-school cycle. The panel label is سیکل. */
    public const CYCLE = 'cycle';

    /** High-school diploma. The panel label is دیپلم. */
    public const DIPLOMA = 'diploma';

    /** Associate degree. The panel label is فوق دیپلم. */
    public const ASSOCIATE = 'associate';

    /** Bachelor's degree. The panel label is لیسانس. */
    public const BACHELOR = 'bachelor';

    /** Master's degree. The panel label is فوق لیسانس. */
    public const MASTER = 'master';

    /** Doctorate. The panel label is دکتری. */
    public const DOCTORATE = 'doctorate';

    /**
     * Persian labels for the degree field.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::NONE => 'بدون مدرک',
            self::CYCLE => 'سیکل',
            self::DIPLOMA => 'دیپلم',
            self::ASSOCIATE => 'فوق دیپلم',
            self::BACHELOR => 'لیسانس',
            self::MASTER => 'فوق لیسانس',
            self::DOCTORATE => 'دکتری',
        ];
    }
}
