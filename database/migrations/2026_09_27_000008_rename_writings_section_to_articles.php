<?php

use App\Auth\Section;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Renames the writings section key to articles.
 *
 * Extending:
 * - The page and the permission key both live as articles. The Persian label stays نوشته‌ها.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permission = Permission::query()
            ->where('name', 'writings')
            ->where('guard_name', Section::GUARD)
            ->first();

        if ($permission instanceof Permission) {
            $permission->name = 'articles';
            $permission->save();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Section::ensure();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permission = Permission::query()
            ->where('name', 'articles')
            ->where('guard_name', Section::GUARD)
            ->first();

        if ($permission instanceof Permission) {
            $permission->name = 'writings';
            $permission->save();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
