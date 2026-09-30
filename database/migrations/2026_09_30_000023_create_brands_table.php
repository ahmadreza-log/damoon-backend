<?php

use App\Auth\Section;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brands (برندها) in the content group, and the brands section permission.
 *
 * content holds the description as Tiptap JSON, like articles. features is the list of
 * brand features from the repeater. logo and catalog are public disk paths from the
 * media library. SEO lives in seo_meta, like articles and pages.
 *
 * Extending:
 * - The form is BrandResource. A new column belongs here, on Brand Fillable, and on the form together.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('english')->nullable();
            $table->jsonb('content')->nullable();
            $table->jsonb('features')->nullable();
            $table->string('website')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('catalog')->nullable();
            $table->string('download')->nullable();
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        Section::ensure();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
