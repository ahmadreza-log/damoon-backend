<?php

use App\Auth\Section;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The API keys table and the api section permission.
 *
 * Every /v1 request needs one active key in the X-Api-Key header. Only the sha256 hash of
 * the key is stored in token; hint keeps its last characters so the panel can tell keys
 * apart. origin is the one site (scheme://host[:port]) whose browser requests the key opens.
 *
 * Extending:
 * - A new rule on a key (an expiry date, a route list) is a column here and a check in ApiKey::allows.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('origin');
            $table->string('token', 64)->unique();
            $table->string('hint', 12);
            $table->boolean('active')->default(true);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Section::ensure();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
