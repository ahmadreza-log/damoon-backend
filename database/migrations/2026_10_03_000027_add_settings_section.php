<?php

use App\Auth\Section;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the settings section so the general settings page can be granted like the others.
 *
 * Extending:
 * - The permission key is settings. The Persian label is تنظیمات عمومی.
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
