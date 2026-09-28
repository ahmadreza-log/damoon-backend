<?php

use App\Auth\Section;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the pages section so the pages list can be granted like the others.
 *
 * Extending:
 * - The permission key is pages. The Persian label is برگه‌ها.
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
        // The section list is rebuilt from Section::keys. Removing one row here
        // would be replaced the next time ensure runs.
    }
};
