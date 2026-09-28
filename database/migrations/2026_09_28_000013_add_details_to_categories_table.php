<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives categories a slug, a parent, a description, sidebar banners, and questions.
 *
 * Existing rows get a slug from their name, with -2, -3 when two names give the same slug.
 *
 * Extending:
 * - The form is CategoryResource. A new column belongs here, on Category Fillable, and on the form together.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
            $table->foreignId('parent_id')->nullable()->after('slug')->constrained('categories')->nullOnDelete();
            $table->text('description')->nullable();
            $table->jsonb('banners')->nullable();
            $table->jsonb('questions')->nullable();
        });

        $taken = [];

        DB::table('categories')->orderBy('id')->each(function (object $row) use (&$taken): void {
            $base = trim((string) preg_replace('/-+/u', '-', (string) preg_replace('/\s+/u', '-', trim((string) $row->name))), '-');
            $base = $base !== '' ? $base : 'category';
            $slug = $base;
            $count = 2;

            while (in_array($slug, $taken, true)) {
                $slug = $base.'-'.$count;
                $count++;
            }

            $taken[] = $slug;
            DB::table('categories')->where('id', $row->id)->update(['slug' => $slug]);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['slug', 'description', 'banners', 'questions']);
        });
    }
};
