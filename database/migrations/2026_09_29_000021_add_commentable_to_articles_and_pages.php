<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets each article and page say whether visitors may send comments (اجازه به ارسال دیدگاه).
 *
 * commentable is on by default, like WordPress, so existing articles and pages stay open.
 *
 * Extending:
 * - Comments themselves get their own table; this flag only tells the site whether to show the form.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['articles', 'pages'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->boolean('commentable')->default(true);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['articles', 'pages'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('commentable');
            });
        }
    }
};
