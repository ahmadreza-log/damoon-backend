<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sanctum's token table, shared by staff and customers.
 *
 * tokenable points at the owner of each token, a User or a Customer. token is the
 * SHA-256 hash of the secret; the plain value is only shown once when it is issued.
 * abilities separates panel cookies from customer API tokens, and expires_at lets
 * AccessTokens refuse and refresh tokens by age.
 *
 * Extending:
 * - Issue and check tokens through App\Auth\AccessTokens rather than writing rows here directly.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
