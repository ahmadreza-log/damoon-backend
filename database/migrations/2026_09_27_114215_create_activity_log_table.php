<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The table of the spatie/laravel-activitylog package.
 *
 * Each row describes one event: what happened (description and event), the record it
 * happened to (subject), who did it (causer), and the changed attributes and extra
 * properties as JSON. log_name groups rows into separate logs.
 *
 * Extending:
 * - Settings such as the default log name and how long rows are kept live in config/activitylog.php.
 * - The package ships no down(); roll this table back with a new migration if the package is removed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();
        });
    }
};
