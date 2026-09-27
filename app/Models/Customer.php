<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A customer account for the API.
 *
 * Tokens on this model have only the api ability and cannot open the staff panel.
 * The customer guard in config/auth.php points at this model.
 *
 * Extending:
 * - Add a new field in Fillable, casts, the migration, AccountFields, and the me response together.
 * - If customer authentication changes, update AccessTokens and the sanctum-customer guard too.
 */
#[Fillable(['username', 'email', 'phone', 'firstname', 'lastname', 'password'])]
#[Hidden(['password', 'remember_token', 'token'])]
class Customer extends Authenticatable
{
    /** @use HasFactory<CustomerFactory> */
    use HasApiTokens, HasFactory;

    /**
     * Display name built from the first name and last name.
     */
    public function name(): string
    {
        return trim($this->firstname.' '.$this->lastname);
    }

    /**
     * Column casts. password is always stored hashed.
     *
     * Eloquent owns this method name.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
