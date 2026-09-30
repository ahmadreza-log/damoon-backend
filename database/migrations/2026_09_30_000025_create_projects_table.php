<?php

use App\Auth\Section;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects (پروژه‌ها) in the content group, the similar projects list, and the projects section permission.
 *
 * content holds the description as Tiptap JSON, like articles. services is a list of
 * service names. employer, position, testimony, and voice are the client's testimonial:
 * name, job title, text, and a voice message file. logo and voice are public disk paths.
 *
 * Extending:
 * - The form is ProjectResource. A new column belongs here, on Project Fillable, and on the form together.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->jsonb('content')->nullable();
            $table->string('logo')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->jsonb('services')->nullable();
            $table->string('industry')->nullable();
            $table->string('duration')->nullable();
            $table->string('location')->nullable();
            $table->string('employer')->nullable();
            $table->string('position')->nullable();
            $table->text('testimony')->nullable();
            $table->string('voice')->nullable();
            $table->boolean('commentable')->default(true);
            $table->timestamps();
        });

        Schema::create('project_similar', function (Blueprint $table): void {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('similar_id')->constrained('projects')->cascadeOnDelete();
            $table->primary(['project_id', 'similar_id']);
        });

        Section::ensure();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_similar');
        Schema::dropIfExists('projects');
    }
};
