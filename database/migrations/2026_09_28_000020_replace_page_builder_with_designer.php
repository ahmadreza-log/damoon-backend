<?php

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves pages from the Redberry block list to the GrapesJS page builder (صفحه‌ساز).
 *
 * design holds the GrapesJS project (components and styles as JSON), markup the HTML it
 * exports, and style its CSS. Blocks saved with the old builder are written into markup
 * as plain HTML sections, in page order, so the new builder opens with them and nothing
 * typed before is lost. The old table goes after that.
 *
 * Extending:
 * - The builder itself is App\Filament\Resources\Pages\Pages\DesignPage and resources/js/designer.js.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->jsonb('design')->nullable();
            $table->longText('markup')->nullable();
            $table->longText('style')->nullable();
        });

        if (! Schema::hasTable('page_builder_blocks')) {
            return;
        }

        DB::table('page_builder_blocks')
            ->where('page_builder_blockable_type', 'App\\Models\\Page')
            ->orderBy('order')
            ->get()
            ->groupBy('page_builder_blockable_id')
            ->each(function ($blocks, $page): void {
                $html = $blocks
                    ->map(fn (object $block): string => self::section((array) json_decode((string) $block->data, true)))
                    ->implode("\n");

                DB::table('pages')->where('id', $page)->update(['markup' => $html]);
            });

        Schema::drop('page_builder_blocks');
    }

    /**
     * Reverse the migrations. Pages laid out with the new builder are not turned back into blocks.
     */
    public function down(): void
    {
        Schema::create('page_builder_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('block_type');
            $table->unsignedTinyInteger('order')->index();
            $table->morphs('page_builder_blockable', indexName: 'page_builder_blockable_index');
            $table->jsonb('data')->nullable();
            $table->timestamps();
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn(['design', 'markup', 'style']);
        });
    }

    /**
     * One old block as a plain HTML section: headings, text, pictures, links, and nested rows.
     *
     * @param  array<string, mixed>  $data
     */
    private static function section(array $data): string
    {
        return '<section style="padding:32px 16px">'.self::parts($data).'</section>';
    }

    /**
     * The fields of one block or one repeater row as HTML.
     *
     * @param  array<string, mixed>  $data
     */
    private static function parts(array $data): string
    {
        $html = '';

        foreach ($data as $key => $value) {
            if (is_array($value) && in_array($key, ['text', 'answer'], true)) {
                $html .= RichContentRenderer::make($value)->toUnsafeHtml();

                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $html .= match (true) {
                        is_string($item) && $item !== '' => '<img src="/storage/'.e($item).'" alt="" style="max-width:100%">',
                        is_array($item) => '<div>'.self::parts($item).'</div>',
                        default => '',
                    };
                }

                continue;
            }

            if (! is_string($value) || $value === '') {
                continue;
            }

            $html .= match ($key) {
                'heading', 'title', 'question' => '<h2>'.e($value).'</h2>',
                'text', 'answer', 'code' => $value,
                'image' => '<img src="/storage/'.e($value).'" alt="" style="max-width:100%">',
                'link', 'url' => '<a href="'.e($value).'">'.e(is_string($data['button'] ?? null) && $data['button'] !== '' ? $data['button'] : $value).'</a>',
                'button', 'language', 'style', 'columns' => '',
                default => '<p>'.e($value).'</p>',
            };
        }

        return $html;
    }
};
