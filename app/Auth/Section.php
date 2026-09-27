<?php

namespace App\Auth;

use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Panel sections a staff account may open.
 *
 * Each section is one Spatie permission. The edit user page stores the chosen
 * list on the user. The owner and developer roles always receive every section.
 *
 * Extending:
 * - Add a constant and a Persian label in options.
 * - Check that permission from the matching Filament page or policy.
 * - Call ensure before assigning the new permission so the row exists.
 */
class Section
{
    /** The panel home page. */
    public const HOME = 'home';

    /** The staff users resource. */
    public const USERS = 'users';

    /** The customers resource. */
    public const CUSTOMERS = 'customers';

    /** The roles resource. */
    public const ROLES = 'roles';

    /** Guard used by the staff panel. */
    public const GUARD = 'web';

    /**
     * Persian labels shown on the user edit page and in the users table.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::HOME => 'پیشخوان',
            self::USERS => 'کاربران',
            self::CUSTOMERS => 'مشتریان',
            self::ROLES => 'نقش‌ها',
        ];
    }

    /**
     * Permission names, in the same order as the edit page.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::options());
    }

    /**
     * Creates any missing section permission and the fixed roles.
     *
     * Safe to call more than once. The owner and developer roles are given every
     * section so a role check and a direct permission check agree.
     */
    public static function ensure(): void
    {
        foreach (self::keys() as $key) {
            Permission::findOrCreate($key, self::GUARD);
        }

        Role::settle();
    }
}
