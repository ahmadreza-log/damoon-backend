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

/**
 * Covers sign-in for both kinds of accounts, all built on Sanctum tokens.
 *
 * Staff open the panel with an httpOnly panel_token cookie that carries a token with
 * the panel ability; a plain session login is not enough. Customers use the /v1 API
 * with a bearer token that has the api ability. A token of one kind must never open
 * the other side. The tests also check that dates show in the Shamsi calendar.
 *
 * Extending:
 * - Token rules live in App\Auth\AccessTokens; the panel cookie is read by AuthenticatePanelToken.
 */
class SanctumAuthenticationTest extends TestCase
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
     * Only a staff token with the panel ability opens the panel.
     *
     * A session login without the cookie and a customer API token in the cookie are
     * both sent to the login page. With a valid panel cookie the dashboard, users list,
     * and customers list open. The lists show the last login in the Shamsi calendar with
     * Persian digits and never the Gregorian month name.
     */
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

    /**
     * Logging in queues the panel cookie, and logging out revokes its token.
     *
     * Login records last_login and creates exactly one token. The queued cookie opens
     * the panel on its own. After logout the token is deleted, so the same cookie is
     * sent back to the login page.
     */
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

    /**
     * The login field accepts an email address as well as a username.
     */
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

    /**
     * The customer API login returns a bearer token that only works on customer routes.
     *
     * /v1/auth/login returns access_token, token_type Bearer, and expires_in, and the
     * token opens /v1/auth/me. A staff panel token sent as a bearer token is refused,
     * and so is a wrong password. Auth::forgetGuards() clears the guard cached by the
     * previous request so the next request is checked from scratch.
     */
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
