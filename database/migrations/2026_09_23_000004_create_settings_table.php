<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site settings written once by the install page.
 *
 * title is the site name shown as the panel brand; description is the site summary.
 * installed_at marks a finished install. While it is empty, EnsureInstalled sends
 * every panel request to /install.
 *
 * Extending:
 * - Add new site-wide settings as columns here through a new migration and in Setting's fillable list.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
