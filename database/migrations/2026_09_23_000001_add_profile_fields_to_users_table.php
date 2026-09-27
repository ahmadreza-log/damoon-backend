<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('id');
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->string('firstname')->after('phone');
            $table->string('lastname')->after('firstname');
            $table->timestamp('last_login')->nullable()->after('lastname');
            $table->ipAddress('last_login_ip')->nullable()->after('last_login');
            $table->string('code', 32)->nullable()->unique()->after('last_login_ip');
            $table->string('token', 80)->nullable()->unique()->after('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['phone']);
            $table->dropUnique(['code']);
            $table->dropUnique(['token']);
            $table->dropColumn([
                'username',
                'phone',
                'firstname',
                'lastname',
                'last_login',
                'last_login_ip',
                'code',
                'token',
            ]);
            $table->string('name')->after('id');
        });
    }
};
