<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SectionAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->create([
            'title' => 'دامون',
            'description' => 'سامانه مدیریت محتوا',
            'installed_at' => now(),
        ]);
    }

    public function test_edit_page_saves_the_sections_a_user_may_open(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $member = User::factory()->create([
            'username' => 'member_user',
            'email' => 'member@example.com',
            'phone' => '09120000002',
        ]);
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/users/'.$member->getKey().'/edit')
            ->assertOk()
            ->assertSee('دسترسی بخش‌ها')
            ->assertSee('پیشخوان')
            ->assertSee('کاربران')
            ->assertSee('مشتریان');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditUser::class, ['record' => $member->getKey()])
            ->fillForm([
                'sections' => [Section::HOME, Section::USERS],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();

        $this->assertTrue($member->can(Section::HOME));
        $this->assertTrue($member->can(Section::USERS));
        $this->assertFalse($member->can(Section::CUSTOMERS));
    }

    public function test_a_user_without_a_section_cannot_open_that_page(): void
    {
        $owner = User::factory()->create([
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

        $this->assertTrue($owner->fresh()->owner());
        $this->assertFalse($member->fresh()->owner());

        $memberToken = app(AccessTokens::class)->issue($member, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $memberToken)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('کاربران')
            ->assertDontSee('/admin/customers');

        $this->withCookie((string) config('sanctum.panel_cookie'), $memberToken)
            ->get('/admin/customers')
            ->assertForbidden();

        $this->withCookie((string) config('sanctum.panel_cookie'), $memberToken)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_the_owner_sees_every_section_in_the_panel(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $ownerToken = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $ownerToken)
            ->get('/admin')
            ->assertOk()
            ->assertSee('پیشخوان')
            ->assertSee('کاربران')
            ->assertSee('مشتریان');
    }
}
