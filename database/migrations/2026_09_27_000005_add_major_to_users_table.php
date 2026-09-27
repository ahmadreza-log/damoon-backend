<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Field of study beside the education level.
 *
 * degree used to be free text. Known Persian labels become the English keys
 * the dropdown stores. Anything else is cleared so the select stays valid.
 *
 * Extending:
 * - The form controls are Fields::staff. A new level belongs on App\Models\Degree.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('major')->nullable()->after('degree');
        });

        $map = [
            'بدون مدرک' => 'none',
            'سیکل' => 'cycle',
            'دیپلم' => 'diploma',
            'فوق دیپلم' => 'associate',
            'کاردانی' => 'associate',
            'لیسانس' => 'bachelor',
            'کارشناسی' => 'bachelor',
            'فوق لیسانس' => 'master',
            'کارشناسی ارشد' => 'master',
            'دکتری' => 'doctorate',
            'دکترا' => 'doctorate',
        ];

        foreach ($map as $label => $key) {
            DB::table('users')->where('degree', $label)->update(['degree' => $key]);
        }

        DB::table('users')
            ->whereNotNull('degree')
            ->whereNotIn('degree', array_values(array_unique($map)))
            ->update(['degree' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $map = [
            'none' => 'بدون مدرک',
            'cycle' => 'سیکل',
            'diploma' => 'دیپلم',
            'associate' => 'فوق دیپلم',
            'bachelor' => 'لیسانس',
            'master' => 'فوق لیسانس',
            'doctorate' => 'دکتری',
        ];

        foreach ($map as $key => $label) {
            DB::table('users')->where('degree', $key)->update(['degree' => $label]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('major');
        });
    }
};
