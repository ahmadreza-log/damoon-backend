<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Page builder blocks (بلوک‌های صفحه‌ساز), in the table the Redberry package expects.
 *
 * Each row is one section of a page: block_type is the block class, order is its
 * place on the page, and data holds the filled fields. The owner is polymorphic, so
 * there is no foreign key and Page deletes its own blocks.
 *
 * Extending:
 * - Another model can own blocks through HasPageBuilder without changing this table.
 * - Block classes live in app/Filament/Builder.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('page_builder_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('block_type');
            $table->unsignedTinyInteger('order')->index();
            $table->morphs('page_builder_blockable', indexName: 'page_builder_blockable_index');
            $table->jsonb('data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_builder_blocks');
    }
};
