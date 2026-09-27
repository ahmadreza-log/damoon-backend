<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

/**
 * A staff account for the panel.
 *
 * The first record created is the owner. The owner role cannot be transferred or deleted.
 * Panel sign-in uses a Sanctum token with the panel ability, not a plain session.
 *
 * Extending:
 * - Store a new role as a string in the roles column. Only the owner value is locked.
 * - Add a new field in Fillable, casts, the migration, and AccountFields together.
 */
#[Fillable(['username', 'email', 'phone', 'firstname', 'lastname', 'password', 'roles'])]
#[Hidden(['password', 'remember_token', 'token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** The only role the application assigns itself. Forms cannot change it. */
    public const ROLE_OWNER = 'owner';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Locks the owner role on create, update, and delete.
     *
     * Eloquent owns this method name.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->roles = static::grant($user->roles);
        });

        static::updating(function (User $user): void {
            $user->roles = static::protect($user);
        });

        static::deleting(function (User $user): bool {
            return ! $user->owner();
        });
    }

    /**
     * Every staff account may open the panel. The real limit is the panel token.
     *
     * The FilamentUser contract owns this method name.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Creates the first user inside a transaction with a table lock.
     *
     * Without the lock, two install requests can both see an empty table and both
     * become the owner. Later updates do not need the lock.
     *
     * Eloquent owns this method name.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($options): bool {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('LOCK TABLE users IN SHARE ROW EXCLUSIVE MODE');
            }

            return parent::save($options);
        });
    }

    /**
     * Whether this account is the single owner.
     */
    public function owner(): bool
    {
        return in_array(self::ROLE_OWNER, $this->roles ?? [], true);
    }

    /**
     * The name shown at the top of the panel: first name and last name.
     *
     * The HasName contract owns this method name.
     */
    public function getFilamentName(): string
    {
        return trim($this->firstname.' '.$this->lastname);
    }

    /**
     * Prepares roles for a user that is about to be created.
     *
     * An incoming owner role is removed. Owner is added only when no user exists yet.
     *
     * @return array<int, string>
     */
    private static function grant(mixed $roles): array
    {
        $roles = static::strip(static::clean($roles));

        if (! static::query()->exists()) {
            $roles[] = self::ROLE_OWNER;
        }

        return array_values(array_unique($roles));
    }

    /**
     * On update, keeps owner only for the account that already had it.
     *
     * @return array<int, string>
     */
    private static function protect(User $user): array
    {
        $roles = static::strip(static::clean($user->roles));

        if (in_array(self::ROLE_OWNER, static::clean($user->getOriginal('roles')), true)) {
            $roles[] = self::ROLE_OWNER;
        }

        return array_values(array_unique($roles));
    }

    /**
     * Removes the owner role so a form cannot move it to someone else.
     *
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    private static function strip(array $roles): array
    {
        return array_values(array_filter(
            $roles,
            fn (string $role) => $role !== self::ROLE_OWNER,
        ));
    }

    /**
     * Turns the raw roles value into a list of strings.
     *
     * The roles column is sometimes an array and sometimes a JSON string, depending on
     * whether the cast has already run.
     *
     * @return array<int, string>
     */
    private static function clean(mixed $roles): array
    {
        if (is_string($roles)) {
            $decoded = json_decode($roles, true);
            $roles = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($roles)) {
            return [];
        }

        return array_values(array_filter($roles, fn (mixed $role) => is_string($role)));
    }

    /**
     * Column casts.
     *
     * roles must stay an array so owner and protect compare it correctly.
     * password is stored hashed. Do not assign the raw value back onto the model.
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
            'roles' => 'array',
            'password' => 'hashed',
        ];
    }
}
