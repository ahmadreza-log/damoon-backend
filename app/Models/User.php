<?php

namespace App\Models;

use App\Auth\RoleName;
use App\Auth\Section;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\Traits\HasRoles;

/**
 * A staff account for the panel.
 *
 * The first record created is the owner. That role cannot be transferred or deleted.
 * Which panel sections someone else may open is stored as Spatie permissions.
 * Panel sign-in uses a Sanctum token with the panel ability, not a plain session.
 *
 * Extending:
 * - Add a new field in Fillable, casts, the migration, and AccountFields together.
 * - Add a panel section in App\Auth\Section, then check it from the page or policy.
 * - Do not write the owner role from a form. created and the role events keep it.
 */
#[Fillable(['username', 'email', 'phone', 'firstname', 'lastname', 'password'])]
#[Hidden(['password', 'remember_token', 'token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** The only role the application assigns itself. Forms cannot change it. */
    public const ROLE_OWNER = RoleName::OWNER;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Stops the owner-role listeners from reacting to their own corrections.
     */
    private static bool $busy = false;

    /**
     * Gives the owner role to the first user and keeps that role in place.
     *
     * Eloquent owns this method name.
     */
    protected static function booted(): void
    {
        static::created(function (User $user): void {
            Section::ensure();

            if (static::query()->whereKeyNot($user->getKey())->role(self::ROLE_OWNER)->exists()) {
                return;
            }

            $user->assignRole(self::ROLE_OWNER);
            $user->grant(Section::keys());
        });

        static::deleting(function (User $user): bool {
            return ! $user->owner();
        });

        Event::listen(RoleAttachedEvent::class, function (RoleAttachedEvent $event): void {
            static::hold($event);
        });

        Event::listen(RoleDetachedEvent::class, function (RoleDetachedEvent $event): void {
            static::keep($event);
        });
    }

    /**
     * Every staff account may open the panel. Section policies decide the pages.
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
        if (! $this->exists) {
            return false;
        }

        return $this->hasRole(self::ROLE_OWNER);
    }

    /**
     * Panel sections this account may open.
     *
     * The owner always receives every section. Other accounts only receive
     * the permissions stored from the edit page.
     *
     * @return array<int, string>
     */
    public function sections(): array
    {
        if ($this->owner()) {
            return Section::keys();
        }

        return array_values(array_intersect(
            Section::keys(),
            $this->getPermissionNames()->all(),
        ));
    }

    /**
     * Stores the panel sections chosen on the edit page.
     *
     * Unknown names are ignored. The owner always keeps every section, even
     * when the form sends a shorter list.
     *
     * @param  array<int, mixed>  $sections
     */
    public function grant(array $sections): void
    {
        Section::ensure();

        $picked = array_values(array_intersect(Section::keys(), array_filter($sections, 'is_string')));

        $this->syncPermissions($this->owner() ? Section::keys() : $picked);
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
     * Removes the owner role when it is given to someone who is not the owner.
     *
     * Spatie fires this after the role row is attached. A second owner is detached again.
     */
    private static function hold(RoleAttachedEvent $event): void
    {
        if (static::$busy || ! $event->model instanceof self || ! static::about($event->rolesOrIds)) {
            return;
        }

        if (! static::query()->whereKeyNot($event->model->getKey())->role(self::ROLE_OWNER)->exists()) {
            return;
        }

        static::$busy = true;

        try {
            $event->model->removeRole(self::ROLE_OWNER);
        } finally {
            static::$busy = false;
        }
    }

    /**
     * Puts the owner role back when it is removed from the account that holds it.
     *
     * If another account already has the role, this does nothing.
     */
    private static function keep(RoleDetachedEvent $event): void
    {
        if (static::$busy || ! $event->model instanceof self || ! static::about($event->rolesOrIds)) {
            return;
        }

        if (static::query()->role(self::ROLE_OWNER)->exists()) {
            return;
        }

        static::$busy = true;

        try {
            $event->model->assignRole(self::ROLE_OWNER);
            $event->model->grant(Section::keys());
        } finally {
            static::$busy = false;
        }
    }

    /**
     * Whether a Spatie role event is about the owner role.
     *
     * The package passes role ids, role models, or role names depending on the caller.
     */
    private static function about(mixed $roles): bool
    {
        $items = $roles instanceof Collection ? $roles->all() : Arr::wrap($roles);

        foreach ($items as $role) {
            if ($role instanceof Role && $role->name === self::ROLE_OWNER) {
                return true;
            }

            if ($role === self::ROLE_OWNER) {
                return true;
            }

            if (is_int($role) || (is_string($role) && ctype_digit($role))) {
                $name = RoleModel::query()->whereKey($role)->value('name');

                if ($name === self::ROLE_OWNER) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Column casts.
     *
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
            'password' => 'hashed',
        ];
    }
}
