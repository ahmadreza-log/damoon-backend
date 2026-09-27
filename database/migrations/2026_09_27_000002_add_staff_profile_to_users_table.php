<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff profile columns on users.
 *
 * personnel is the personnel code. national is the Iranian national id.
 * job, degree, and gender are optional profile text. Customers do not get these columns.
 *
 * Extending:
 * - A new profile field needs a column here and an entry in Fields::staff.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('personnel', 32)->nullable()->unique();
            $table->string('national', 10)->nullable()->unique();
            $table->string('job')->nullable();
            $table->string('degree')->nullable();
            $table->string('gender', 16)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['personnel']);
            $table->dropUnique(['national']);
            $table->dropColumn(['personnel', 'national', 'job', 'degree', 'gender']);
        });
    }
};
