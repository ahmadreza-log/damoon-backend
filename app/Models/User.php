<?php

namespace App\Models;

use App\Auth\RoleName;
use App\Auth\Section;
use App\Support\Sizes;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use App\Models\Role as RoleModel;
use Spatie\Permission\Traits\HasRoles;

/**
 * A staff account for the panel.
 *
 * The first record created is the owner. That role cannot be transferred or deleted.
 * Which panel sections someone else may open is stored as Spatie permissions.
 * Panel sign-in uses a Sanctum token with the panel ability, not a plain session.
 *
 * Extending:
 * - Add a shared account field in Fillable, the migration, and Fields::account together.
 * - A staff-only field belongs in Fields::staff, Fillable, and a users migration.
 * - The avatar is Fields::avatar. getFilamentAvatarUrl owns the panel image.
 * - active is Fields::status. A false value blocks the panel. The owner is forced back to true.
 * - Add a panel section in App\Auth\Section, then check it from the page or policy.
 * - Do not write the owner role from a form. created and the role events keep it.
 */
#[Fillable(['username', 'email', 'phone', 'firstname', 'lastname', 'password', 'personnel', 'national', 'job', 'degree', 'major', 'gender', 'avatar', 'active'])]
#[Hidden(['password', 'remember_token', 'token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar, HasName
{
    /** The only role the application assigns itself. Forms cannot change it. */
    public const ROLE_OWNER = RoleName::OWNER;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * New accounts may open the panel until someone switches them off.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => true,
    ];

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

        static::saving(function (User $user): void {
            if ($user->owner()) {
                $user->active = true;
            }
        });

        static::saved(function (User $user): void {
            if ($user->isDirty('avatar') && is_string($user->avatar) && $user->avatar !== '') {
                Sizes::make($user->avatar);
            }
        });

        static::updating(function (User $user): void {
            static::forget($user);
        });

        static::updated(function (User $user): void {
            static::halt($user);
        });

        static::deleted(function (User $user): void {
            if (is_string($user->avatar) && $user->avatar !== '') {
                Storage::disk('public')->delete($user->avatar);
                Sizes::drop($user->avatar);
            }
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
     * An inactive account cannot open the panel. Section policies still decide the pages.
     *
     * The owner is kept active in saving, so this stays true for that account.
     * The FilamentUser contract owns this method name.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->active;
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
     * The personal avatar shown in the panel.
     *
     * The HasAvatar contract owns this method name. An empty path uses the shared default image.
     * The square thumb size is used when it was built.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        if (is_string($this->avatar) && $this->avatar !== '') {
            $disk = Storage::disk('public');

            if ($disk instanceof FilesystemAdapter && $disk->exists($this->avatar)) {
                return $disk->url(Sizes::pick($this->avatar, 'thumb'));
            }
        }

        return '/images/default-avatar.png';
    }

    /**
     * Deletes the previous avatar file and its sizes when a new one replaces it.
     */
    private static function forget(User $user): void
    {
        if (! $user->isDirty('avatar')) {
            return;
        }

        $previous = $user->getOriginal('avatar');

        if (! is_string($previous) || $previous === '' || $previous === $user->avatar) {
            return;
        }

        Storage::disk('public')->delete($previous);
        Sizes::drop($previous);
    }

    /**
     * Drops every panel token when the account is switched off.
     *
     * A cookie that is already in the browser then fails on the next request.
     */
    private static function halt(User $user): void
    {
        if (! $user->wasChanged('active') || $user->active) {
            return;
        }

        $user->tokens()->delete();
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
            'active' => 'boolean',
        ];
    }
}
