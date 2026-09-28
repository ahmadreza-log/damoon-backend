<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the first version of roles: a JSON list of role names on each user.
 *
 * Every user starts with an empty list. 2026_09_27_000001_move_roles_to_permissions
 * later moves these names into the Spatie permission tables.
 *
 * Extending:
 * - Do not read this column in new code; roles now live in the Spatie tables.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->default('[]');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }
};
