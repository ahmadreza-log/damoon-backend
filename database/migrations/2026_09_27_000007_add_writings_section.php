<?php

use App\Auth\Section;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the articles section and gives it to the fixed roles.
 *
 * Extending:
 * - A new panel section belongs in App\Auth\Section. ensure copies it onto developer and owner.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Section::ensure();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The section list lives in code. Removing the permission row is not required to roll back.
    }
};
