<?php

use App\Auth\Section;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitor comments (دیدگاه‌ها) on articles and pages, and the comments section permission.
 *
 * subject is the article or page a comment belongs to. parent_id points to the comment it
 * answers; replies are kept one level deep, so it is always a top-level comment. A comment
 * comes from a guest (name and email), a signed-in customer (customer_id), or a staff
 * member answering from the panel (user_id). status is pending, approved, or spam; only
 * approved comments reach the site. Deleting a comment deletes its replies.
 *
 * Extending:
 * - The model is App\Models\Comment and the panel page is CommentResource.
 * - The permission key is comments. The Persian label is دیدگاه‌ها.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->text('body');
            $table->string('status')->default('pending')->index();
            $table->string('ip', 45)->nullable();
            $table->string('agent')->nullable();
            $table->timestamps();
        });

        Section::ensure();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
