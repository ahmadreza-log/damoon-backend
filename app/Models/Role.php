<?php

namespace App\Models;

use App\Auth\RoleName;
use App\Auth\Section;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A panel role and the sections it may open.
 *
 * The key is the Spatie name. The Persian name is label.
 * Developer and owner always exist and always keep every section.
 *
 * Extending:
 * - A fixed role belongs in RoleName::fixed. settle creates it.
 * - A section belongs in App\Auth\Section. hold copies the full list onto the fixed roles.
 * - Do not delete or narrow developer or owner from a form. saving and deleting refuse that.
 */
class Role extends SpatieRole
{
    /**
     * Keeps the two fixed roles complete, and blocks deleting them.
     *
     * Eloquent owns this method name.
     */
    protected static function booted(): void
    {
        static::saving(function (Role $role): void {
            $role->seal();
        });

        static::saved(function (Role $role): void {
            if ($role->locked()) {
                $role->hold();
            }
        });

        static::deleting(function (Role $role): bool {
            return ! $role->locked();
        });
    }

    /**
     * Creates the developer and owner roles and gives each one every section.
     *
     * Safe to call more than once. The label column is filled only after its migration.
     */
    public static function settle(): void
    {
        $labeled = Schema::hasColumn((new static)->getTable(), 'label');

        foreach (RoleName::fixed() as $key => $label) {
            $role = static::findOrCreate($key, Section::GUARD);

            if (! $role instanceof self) {
                continue;
            }

            if ($labeled && $role->label !== $label) {
                $role->label = $label;
                $role->saveQuietly();
            }

            $role->hold();
        }
    }

    /**
     * Whether this role is developer or owner.
     */
    public function locked(): bool
    {
        return array_key_exists((string) $this->name, RoleName::fixed());
    }

    /**
     * Panel sections this role may open.
     *
     * The fixed roles always receive every section.
     *
     * @return array<int, string>
     */
    public function sections(): array
    {
        if ($this->locked()) {
            return Section::keys();
        }

        return array_values(array_intersect(
            Section::keys(),
            $this->permissions()->pluck('name')->all(),
        ));
    }

    /**
     * Stores the sections chosen on the role form.
     *
     * Unknown names are ignored. A fixed role always keeps every section.
     *
     * @param  array<int, mixed>  $sections
     */
    public function grant(array $sections): void
    {
        Section::ensure();

        if ($this->locked()) {
            $this->hold();

            return;
        }

        $picked = array_values(array_intersect(Section::keys(), array_filter($sections, 'is_string')));

        $this->syncPermissions($picked);
    }

    /**
     * Gives a fixed role every current section.
     */
    public function hold(): void
    {
        $need = Section::keys();
        $have = $this->permissions()->pluck('name')->all();

        if (array_diff($need, $have) === [] && array_diff($have, $need) === []) {
            return;
        }

        $this->syncPermissions($need);
    }

    /**
     * Stops a form from renaming a fixed role or changing its guard.
     */
    private function seal(): void
    {
        if (! $this->locked() && ! ($this->exists && array_key_exists((string) $this->getOriginal('name'), RoleName::fixed()))) {
            return;
        }

        $key = $this->name;

        if ($this->exists) {
            $original = $this->getOriginal('name');

            if (is_string($original) && $original !== '') {
                $key = $original;
            }
        }

        $this->name = $key;
        $this->guard_name = Section::GUARD;

        if (! Schema::hasColumn($this->getTable(), 'label')) {
            return;
        }

        $this->label = RoleName::fixed()[$key] ?? $this->label;
    }
}
