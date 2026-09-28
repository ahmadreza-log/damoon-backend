<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes sure exactly one user holds the owner role.
 *
 * When no user is the owner yet, the oldest user becomes the owner.
 * On PostgreSQL a partial unique index on a constant then lets the database itself
 * refuse a second owner: every owner row would share the same index key (true).
 * Other drivers, such as the in-memory SQLite the tests use, skip the index.
 *
 * Extending:
 * - The owner rule is also guarded in User; keep both in step when the owner rule changes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $owner = DB::table('users')
            ->whereJsonContains('roles', 'owner')
            ->exists();

        if (! $owner) {
            $first = DB::table('users')->orderBy('id')->first();

            if ($first !== null) {
                $roles = json_decode($first->roles ?? '[]', true);
                $roles = is_array($roles) ? $roles : [];
                $roles[] = 'owner';

                DB::table('users')->where('id', $first->id)->update([
                    'roles' => json_encode(array_values(array_unique($roles))),
                ]);
            }
        }

        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("CREATE UNIQUE INDEX users_single_owner_idx ON users ((true)) WHERE roles::jsonb @> '[\"owner\"]'::jsonb");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS users_single_owner_idx');
    }
};
