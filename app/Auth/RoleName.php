<?php

namespace App\Auth;

/**
 * Role names stored by Spatie.
 *
 * Only the owner role is assigned by the application. It belongs to the first
 * staff account and cannot be moved.
 *
 * Extending:
 * - Add a new constant here, then create it with Role::findOrCreate and the web guard.
 */
class RoleName
{
    /** The single staff account created first. */
    public const OWNER = 'owner';
}
