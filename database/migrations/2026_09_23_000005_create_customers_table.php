<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers: the site's members, kept apart from staff users.
 *
 * Customers sign in through the API (/v1/auth/login) with a Sanctum bearer token and never
 * open the panel. The columns mirror users: a unique username, email, and optional phone,
 * first and last name, the last sign-in time and IP, and spare unique code and token columns.
 *
 * Extending:
 * - Customer-only fields belong here; staff-only fields belong on users.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->timestamp('last_login')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->string('code', 32)->nullable()->unique();
            $table->string('token', 80)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
