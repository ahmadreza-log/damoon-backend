<?php

use App\Auth\Section;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forms (فرم‌ها) built in the panel, the messages sent through them, the forms settings row,
 * and the forms and inbox section permissions.
 *
 * forms.fields holds the Filament Builder blocks, one per field. entries.answers is a
 * snapshot of each answer with the label it had when sent, so editing a form later does
 * not change old messages. form_settings has one row, read through FormSetting::current.
 *
 * Extending:
 * - A new form column belongs here, on Form Fillable, and on FormResource together.
 * - A new setting is a column on form_settings, a default in FormSetting, and a field on the settings page.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->jsonb('fields')->nullable();
            $table->string('button')->nullable();
            $table->text('message')->nullable();
            $table->jsonb('recipients')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('answers');
            $table->string('status')->default('new')->index();
            $table->string('ip', 45)->nullable();
            $table->string('agent')->nullable();
            $table->string('source', 2048)->nullable();
            $table->timestamps();
        });

        Schema::create('form_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('notify')->default(true);
            $table->jsonb('recipients')->nullable();
            $table->text('message')->nullable();
            $table->unsignedSmallInteger('rate')->default(5);
            $table->unsignedInteger('size')->default(5120);
            $table->unsignedSmallInteger('retention')->nullable();
            $table->timestamps();
        });

        Section::ensure();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_settings');
        Schema::dropIfExists('entries');
        Schema::dropIfExists('forms');
    }
};
