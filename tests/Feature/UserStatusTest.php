<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Auth\Login;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserStatusTest extends TestCase
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

    public function test_switching_a_user_off_blocks_the_panel_and_keeps_the_account(): void
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
            'password' => 'password123',
        ]);
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);
        $member->grant([Section::HOME]);
        $memberToken = app(AccessTokens::class)->issue($member, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/users/'.$member->getKey().'/edit')
            ->assertOk()
            ->assertSee('فعال')
            ->assertSee('غیرفعال')
            ->assertSee('با غیرفعال کردن، ورود این کاربر به پنل قطع می‌شود.');

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('حذف');

        $this->withCookie((string) config('sanctum.panel_cookie'), $memberToken)
            ->get('/admin')
            ->assertOk();

        $this->flushSession();

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/users/'.$member->getKey().'/edit')
            ->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditUser::class, ['record' => $member->getKey()])
            ->fillForm([
                'active' => 0,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();

        $this->assertFalse($member->active);
        $this->assertNotNull(User::query()->find($member->getKey()));
        $this->assertSame(0, $member->tokens()->count());

        $this->flushSession();

        $this->withCookie((string) config('sanctum.panel_cookie'), $memberToken)
            ->get('/admin')
            ->assertRedirect(route('filament.admin.auth.login'));

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'member_user',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['login'])
            ->assertSee('این حساب غیرفعال است.');

        $this->assertSame(0, $member->fresh()->tokens()->count());

        $member->delete();

        $this->assertNull(User::query()->find($member->getKey()));
    }

    public function test_the_owner_stays_active(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);

        $owner->update(['active' => false]);

        $this->assertTrue($owner->fresh()->active);
    }
}
