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

/**
 * Covers the active switch on staff accounts.
 *
 * Switching a user off must cut their panel access at once, revoke their tokens,
 * and block new logins, while keeping the account and its data. The owner can
 * never be switched off.
 *
 * Extending:
 * - The switch is Fields::status(); the checks live in User, Login, and AuthenticatePanelToken.
 */
class UserStatusTest extends TestCase
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
     * An inactive user loses the panel but keeps the account.
     *
     * The edit page shows the active and inactive choices with their warning, and the
     * list still offers delete. The member can open the panel before the switch. After
     * the owner switches them off, every token of the member is revoked, their old
     * cookie is sent to the login page, and logging in again fails with
     * "این حساب غیرفعال است." without issuing a token. The account can still be deleted.
     * flushSession() between requests makes each request rely on the cookie alone.
     */
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

    /**
     * Saving the owner as inactive is ignored, so the site can never lock out its owner.
     */
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
