<?php

use App\Auth\Section;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The site's social links on the settings row, and the socials section permission.
 *
 * settings.socials is a list of links, each with a name, a Blade Icons icon name such as
 * si-instagram, and an address, in the order the site shows them.
 *
 * Extending:
 * - A new field on each link belongs in the repeater on SocialSettings and in Setting::links.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->jsonb('socials')->nullable();
        });

        Section::ensure();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn('socials');
        });
    }
};
