<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an article sit in many categories, the same way it carries many tags.
 *
 * The single category each article had moves into the article_category table,
 * then articles.category_id is dropped.
 *
 * Extending:
 * - Article::categories and Category::articles read this table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('article_category', function (Blueprint $table): void {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'category_id']);
        });

        DB::table('articles')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('article_category')->insert([
                    'article_id' => $row->id,
                    'category_id' => $row->category_id,
                ]);
            });

        Schema::table('articles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
        });
    }

    /**
     * Reverse the migrations. Each article keeps only its first category.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('cover')->constrained()->nullOnDelete();
        });

        DB::table('article_category')
            ->orderBy('article_id')
            ->orderBy('category_id')
            ->get()
            ->unique('article_id')
            ->each(function (object $row): void {
                DB::table('articles')->where('id', $row->article_id)->update(['category_id' => $row->category_id]);
            });

        Schema::dropIfExists('article_category');
    }
};
