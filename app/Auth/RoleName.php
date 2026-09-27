<?php

namespace App\Auth;

/**
 * Role keys stored by Spatie.
 *
 * The owner role belongs to the first staff account and cannot be moved.
 * The developer role is not assigned by itself. Both stay in the roles section
 * and always keep every panel section.
 *
 * Extending:
 * - Add a fixed role in fixed. Role::settle creates it and gives it every section.
 * - A role someone defines in the panel does not need a constant here.
 */
class RoleName
{
    /** The single staff account created first. The panel label is مالک. */
    public const OWNER = 'owner';

    /** Full access, kept beside the owner. The panel label is توسعه‌دهنده. */
    public const DEVELOPER = 'developer';

    /**
     * Roles the panel must always keep, keyed by the stored key.
     *
     * @return array<string, string>
     */
    public static function fixed(): array
    {
        return [
            self::DEVELOPER => 'توسعه‌دهنده',
            self::OWNER => 'مالک',
        ];
    }
}
