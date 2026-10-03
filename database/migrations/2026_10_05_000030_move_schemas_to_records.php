<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Schemas move from the schema builder page onto each record, and the site gets its address.
 *
 * articles, pages, brands, projects, and settings get a schemas column: a list of
 * {type, active, fields} items from the damoon/schema editor; null means the record starts with
 * its model's default types. Active site-wide schemas from the old markups table move onto the
 * settings row before that table is dropped, along with the schemas section permission.
 * settings.url is the public website's address and settings.routes each content type's address
 * pattern, such as {"articles": "/blog/{slug}"}.
 *
 * Extending:
 * - A new content type with schemas needs the column here and Schemable on its model.
 */
return new class extends Migration
{
    /** Tables whose rows carry their own schemas. */
    private const TABLES = ['articles', 'pages', 'brands', 'projects', 'settings'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->jsonb('schemas')->nullable();
            });
        }

        Schema::table('settings', function (Blueprint $table): void {
            $table->string('url')->nullable();
            $table->jsonb('routes')->nullable();
        });

        $this->carry();

        Schema::dropIfExists('markups');

        Permission::query()->where('name', 'schemas')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations. The schema builder page and its rows do not come back.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn(['url', 'routes']);
        });

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('schemas');
            });
        }
    }

    /**
     * Copies the active site-wide schemas from markups onto the latest settings row.
     */
    private function carry(): void
    {
        if (! Schema::hasTable('markups')) {
            return;
        }

        $settings = DB::table('settings')->latest('id')->first();

        if ($settings === null) {
            return;
        }

        $items = DB::table('markups')
            ->where('target', 'site')
            ->where('active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => [
                'type' => (string) $row->type,
                'active' => true,
                'fields' => json_decode((string) $row->fields, true) ?: [],
            ])
            ->all();

        if ($items !== []) {
            DB::table('settings')->where('id', $settings->id)->update(['schemas' => json_encode($items, JSON_UNESCAPED_UNICODE)]);
        }
    }
};
