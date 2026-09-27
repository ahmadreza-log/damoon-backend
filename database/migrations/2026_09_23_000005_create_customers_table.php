<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
