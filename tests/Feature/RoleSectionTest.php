<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\RoleName;
use App\Auth\Section;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the roles page and the sections each role grants.
 *
 * A role has a Persian label, a lowercase key, and a set of sections. The owner and
 * developer roles are fixed: they always keep every section, keep their name and
 * label, and cannot be deleted. The page itself needs the roles section.
 *
 * Extending:
 * - Fixed role names are in App\Auth\RoleName; the rules that protect them live in App\Models\Role.
 */
class RoleSectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Marks the site as installed so EnsureInstalled lets panel requests through.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->create([
            'title' => 'دامون',
            'description' => 'سامانه مدیریت محتوا',
            'installed_at' => now(),
        ]);
    }

    /**
     * The roles list shows the fixed roles, and the create form stores a new role.
     *
     * The key typed as "Editor" is saved in lowercase as "editor", with its label and
     * the chosen sections.
     */
    public function test_the_roles_page_stores_a_custom_role(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/roles')
            ->assertOk()
            ->assertSee('نقش‌ها')
            ->assertSee('توسعه‌دهنده')
            ->assertSee('مالک')
            ->assertSee('developer')
            ->assertSee('owner');

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/roles/create')
            ->assertOk()
            ->assertSee('نام')
            ->assertSee('کلید')
            ->assertSee('دسترسی بخش‌ها')
            ->assertSee('پیشخوان');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateRole::class)
            ->fillForm([
                'label' => 'ویرایشگر',
                'name' => 'Editor',
                'sections' => [Section::HOME],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::query()->where('name', 'editor')->first();

        $this->assertInstanceOf(Role::class, $role);
        $this->assertSame('ویرایشگر', $role->label);
        $this->assertSame([Section::HOME], $role->sections());
    }

    /**
     * The owner and developer roles cannot be changed or removed.
     *
     * The owner edit page explains that the role always has every section. Saving it
     * with a new name, label, and fewer sections still leaves "owner", "مالک", and all
     * sections, and delete() returns false for both fixed roles.
     */
    public function test_fixed_roles_keep_every_section_and_cannot_be_deleted(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);
        $role = Role::query()->where('name', RoleName::OWNER)->first();

        $this->assertInstanceOf(Role::class, $role);
        $this->assertSame('مالک', $role->label);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/roles/'.$role->getKey().'/edit')
            ->assertOk()
            ->assertSee('این نقش همیشه به همه بخش‌ها دسترسی دارد.');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditRole::class, ['record' => $role->getKey()])
            ->fillForm([
                'label' => 'تغییر',
                'name' => 'other',
                'sections' => [Section::HOME],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $role->refresh();

        $this->assertSame(RoleName::OWNER, $role->name);
        $this->assertSame('مالک', $role->label);
        $this->assertEqualsCanonicalizing(Section::keys(), $role->sections());
        $this->assertFalse($role->delete());
        $this->assertNotNull(Role::query()->find($role->getKey()));

        $developer = Role::query()->where('name', RoleName::DEVELOPER)->first();

        $this->assertInstanceOf(Role::class, $developer);
        $this->assertSame('توسعه‌دهنده', $developer->label);
        $this->assertEqualsCanonicalizing(Section::keys(), $developer->sections());
        $this->assertFalse($developer->delete());
    }

    /**
     * A member with only the users section gets 403 on the roles page.
     */
    public function test_a_user_without_the_roles_section_cannot_open_it(): void
    {
        User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $member = User::factory()->create([
            'username' => 'member_user',
            'email' => 'member@example.com',
            'phone' => '09120000002',
        ]);
        $member->grant([Section::USERS]);
        $token = app(AccessTokens::class)->issue($member, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/roles')
            ->assertForbidden();
    }
}
