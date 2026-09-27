<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Filament\Auth\Login;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Support\Shamsi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;
use Tests\TestCase;

class SanctumAuthenticationTest extends TestCase
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

    public function test_panel_requires_a_user_sanctum_cookie(): void
    {
        $logged = Carbon::parse('2026-09-27 08:03:19', 'UTC');
        $user = User::factory()->create([
            'last_login' => $logged,
        ]);
        $customer = Customer::factory()->create([
            'last_login' => $logged,
        ]);
        $shamsi = CalendarUtils::convertNumbers(
            Jalalian::fromCarbon($logged->copy()->timezone(Shamsi::ZONE))->format(Shamsi::TIME),
        );
        $tokens = app(AccessTokens::class);

        $this->actingAs($user)->get('/admin')->assertRedirect(route('filament.admin.auth.login'));

        $api = $tokens->issue($customer, AccessTokens::ABILITY_API, AccessTokens::ABILITY_API, 60);
        $this->withCookie('panel_token', $api)
            ->get('/admin')
            ->assertRedirect(route('filament.admin.auth.login'));

        $panel = $tokens->issue($user, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);
        $this->withCookie('panel_token', $panel)
            ->get('/admin')
            ->assertOk()
            ->assertSee('کاربران')
            ->assertSee('مشتریان');

        $this->withCookie('panel_token', $panel)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee($user->firstname)
            ->assertSee('نام خانوادگی')
            ->assertSee('شماره تلفن')
            ->assertSee('آخرین ورود')
            ->assertSee($shamsi)
            ->assertDontSee('سپتامبر')
            ->assertDontSee('نام کاربری')
            ->assertDontSee('بخش‌ها');

        $this->withCookie('panel_token', $panel)
            ->get('/admin/customers')
            ->assertOk()
            ->assertSee($customer->username)
            ->assertSee('نام کاربری')
            ->assertSee('نام خانوادگی')
            ->assertSee($shamsi);
    }

    public function test_panel_login_issues_a_sanctum_cookie_and_logout_revokes_it(): void
    {
        $user = User::factory()->create([
            'username' => 'staff',
            'password' => 'password123',
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'staff',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->last_login);
        $this->assertSame(1, $user->tokens()->count());

        $queued = collect(cookie()->getQueuedCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'panel_token');

        $this->assertNotNull($queued);

        $this->flushSession();

        $this->withCookie('panel_token', $queued->getValue())
            ->get('/admin')
            ->assertOk();

        $this->withCookie('panel_token', $queued->getValue())
            ->post('/admin/logout')
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertSame(0, $user->tokens()->count());

        $this->flushSession();

        $this->withCookie('panel_token', $queued->getValue())
            ->get('/admin')
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_panel_login_accepts_email(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'password123',
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'staff@example.com',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertRedirect();

        $this->assertNotNull($user->fresh()->last_login);
    }

    public function test_customer_api_login_returns_a_sanctum_bearer_token(): void
    {
        $customer = Customer::factory()->create([
            'username' => 'client',
            'password' => 'password123',
        ]);
        $user = User::factory()->create();
        $tokens = app(AccessTokens::class);

        $login = $this->postJson('/v1/auth/login', [
            'username' => 'client',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['access_token', 'expires_in']);

        $this->withToken($login->json('access_token'))
            ->getJson('/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('username', 'client');

        Auth::forgetGuards();

        $panel = $tokens->issue($user, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withToken($panel)
            ->getJson('/v1/auth/me')
            ->assertUnauthorized();

        $this->postJson('/v1/auth/login', [
            'username' => 'client',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }
}
