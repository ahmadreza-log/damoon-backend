<?php

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the article body from HTML text to a Tiptap JSON document.
 *
 * Existing HTML is converted row by row. down() turns the JSON back into HTML.
 *
 * Extending:
 * - Article::content reads and writes this column. The editor is on ArticleResource.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->jsonb('body')->nullable();
        });

        DB::table('articles')->orderBy('id')->each(function (object $row): void {
            $document = RichContentRenderer::make((string) $row->content)->toArray()
                ?: ['type' => 'doc', 'content' => []];

            DB::table('articles')->where('id', $row->id)->update([
                'body' => json_encode($document, JSON_UNESCAPED_UNICODE),
            ]);
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('content');
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->renameColumn('body', 'content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->longText('body')->nullable();
        });

        DB::table('articles')->orderBy('id')->each(function (object $row): void {
            $document = json_decode((string) $row->content, true);

            DB::table('articles')->where('id', $row->id)->update([
                'body' => is_array($document) ? RichContentRenderer::make($document)->toUnsafeHtml() : '',
            ]);
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('content');
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->renameColumn('body', 'content');
        });
    }
};
