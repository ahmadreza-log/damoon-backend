<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the SEO title and description of articles and pages into seo_meta.
 *
 * The SEO box now comes from rankbeam/laravel-seo, which keeps one seo_meta row per
 * record and locale. Old values are copied into the Persian row, cut to the seo_meta
 * column lengths, and the seo_title and seo_description columns are dropped.
 *
 * Extending:
 * - down puts the columns back and copies the Persian rows into them.
 * - The panel runs in fa, so the rows are written with that locale.
 */
return new class extends Migration
{
    /** Table name to the morph type stored in seo_meta. */
    private const TABLES = [
        'articles' => 'App\Models\Article',
        'pages' => 'App\Models\Page',
    ];

    private const LOCALE = 'fa';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table => $type) {
            DB::table($table)
                ->where(fn ($query) => $query->whereNotNull('seo_title')->orWhereNotNull('seo_description'))
                ->orderBy('id')
                ->each(function (object $row) use ($type): void {
                    $title = $this->cut($row->seo_title, 70);
                    $description = $this->cut($row->seo_description, 160);

                    if ($title === null && $description === null) {
                        return;
                    }

                    DB::table('seo_meta')->updateOrInsert(
                        ['seoable_type' => $type, 'seoable_id' => $row->id, 'locale' => self::LOCALE],
                        ['title' => $title, 'description' => $description, 'created_at' => now(), 'updated_at' => now()],
                    );
                });

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn(['seo_title', 'seo_description']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table => $type) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('seo_title')->nullable();
                $blueprint->text('seo_description')->nullable();
            });

            DB::table('seo_meta')
                ->where('seoable_type', $type)
                ->where('locale', self::LOCALE)
                ->orderBy('id')
                ->each(function (object $meta) use ($table): void {
                    DB::table($table)->where('id', $meta->seoable_id)->update([
                        'seo_title' => $meta->title,
                        'seo_description' => $meta->description,
                    ]);
                });
        }
    }

    /**
     * Trimmed text no longer than the limit, or null when blank.
     */
    private function cut(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
};
