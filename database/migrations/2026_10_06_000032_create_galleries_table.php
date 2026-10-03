<?php

use App\Auth\Section;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Galleries (گالری‌ها) in the content group, and the galleries section permission.
 *
 * kind is image, video, or audio (App\Models\Kind). items is the ordered list of public disk
 * paths chosen from the media library.
 *
 * Extending:
 * - The form is GalleryResource. A new column belongs here, on Gallery Fillable, and on the form together.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('kind', 10)->index();
            $table->text('description')->nullable();
            $table->jsonb('items')->nullable();
            $table->timestamps();
        });

        Section::ensure();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('galleries');
    }
};
