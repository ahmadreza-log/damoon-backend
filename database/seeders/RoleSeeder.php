<?php

namespace Database\Seeders;

use App\Auth\Section;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Sample panel roles: نویسنده for content and پشتیبان for customers and comments.
 *
 * The developer and owner roles come from Role::settle. Running this again keeps
 * one row per role and resets its sections to the list here.
 *
 * Extending:
 * - Another sample role is one more entry in ROLES.
 */
class RoleSeeder extends Seeder
{
    /**
     * Sample roles: key => [Persian label, sections].
     *
     * @var array<string, array{0: string, 1: array<int, string>}>
     */
    public const ROLES = [
        'writer' => ['نویسنده', [Section::HOME, Section::ARTICLES, Section::PAGES, Section::MEDIA, Section::COMMENTS]],
        'support' => ['پشتیبان', [Section::HOME, Section::CUSTOMERS, Section::COMMENTS]],
    ];

    /**
     * Creates the fixed roles and the sample ones with their sections.
     */
    public function run(): void
    {
        Role::settle();

        foreach (self::ROLES as $name => [$label, $sections]) {
            $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => Section::GUARD], ['label' => $label]);
            $role->grant($sections);
        }
    }
}
