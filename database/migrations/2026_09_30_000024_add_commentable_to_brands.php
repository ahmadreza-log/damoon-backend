<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets visitors comment on brands, like articles and pages.
 *
 * commentable is on by default, so existing brands take comments until staff turn it off.
 *
 * Extending:
 * - The switch is «اجازه به ارسال دیدگاه» on BrandResource.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->boolean('commentable')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table): void {
            $table->dropColumn('commentable');
        });
    }
};
