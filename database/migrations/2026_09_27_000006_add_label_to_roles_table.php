<?php

use App\Auth\Section;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persian name for a role, beside the Spatie key.
 *
 * Developer and owner are created with their labels and every section.
 *
 * Extending:
 * - The form fields live on RoleResource. A new fixed role belongs in RoleName::fixed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('label')->nullable()->after('name');
        });

        Section::ensure();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('label');
        });
    }
};
