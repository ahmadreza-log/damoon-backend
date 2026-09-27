<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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

#[Fillable(['username', 'email', 'phone', 'firstname', 'lastname', 'password', 'roles'])]
#[Hidden(['password', 'remember_token', 'token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    public const ROLE_OWNER = 'owner';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->roles = static::rolesForNewUser($user->roles);
        });

        static::updating(function (User $user): void {
            $user->roles = static::rolesPreservingOwner($user);
        });

        static::deleting(function (User $user): bool {
            return ! $user->isOwner();
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

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

    public function isOwner(): bool
    {
        return in_array(self::ROLE_OWNER, $this->roles ?? [], true);
    }

    public function getFilamentName(): string
    {
        return trim($this->firstname.' '.$this->lastname);
    }

    /**
     * @return array<int, string>
     */
    private static function rolesForNewUser(mixed $roles): array
    {
        $roles = static::withoutOwner(static::normalizeRoles($roles));

        if (! static::query()->exists()) {
            $roles[] = self::ROLE_OWNER;
        }

        return array_values(array_unique($roles));
    }

    /**
     * @return array<int, string>
     */
    private static function rolesPreservingOwner(User $user): array
    {
        $roles = static::withoutOwner(static::normalizeRoles($user->roles));

        if (in_array(self::ROLE_OWNER, static::normalizeRoles($user->getOriginal('roles')), true)) {
            $roles[] = self::ROLE_OWNER;
        }

        return array_values(array_unique($roles));
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    private static function withoutOwner(array $roles): array
    {
        return array_values(array_filter(
            $roles,
            fn (string $role) => $role !== self::ROLE_OWNER,
        ));
    }

    /**
     * @return array<int, string>
     */
    private static function normalizeRoles(mixed $roles): array
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
     * Get the attributes that should be cast.
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
