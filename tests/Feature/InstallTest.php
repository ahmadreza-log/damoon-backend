<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Covers the one-time install page at /install.
 *
 * On a fresh database there is no settings row, so the panel sends everyone to the
 * install page. Installing saves the site title and description, creates the owner
 * account, and closes the install page for good.
 *
 * Extending:
 * - A new install field needs a value in the store test and an assertion on where it is saved.
 */
class InstallTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Before install, /admin redirects to /install, which shows the install form.
     */
    public function test_admin_panel_redirects_to_install_until_setup_is_complete(): void
    {
        $this->get('/admin')->assertRedirect(route('install'));

        $this->get('/install')
            ->assertOk()
            ->assertSee('نصب سامانه')
            ->assertSee('عنوان');
    }

    /**
     * Submitting the install form creates the owner and finishes the install.
     *
     * The response redirects to the panel login. The new user is the owner and can log
     * in with the username, the settings row is marked installed with the given title,
     * and /install now redirects to the login page instead of showing the form.
     */
    public function test_install_creates_the_owner_and_allows_login_with_username(): void
    {
        $response = $this->post('/install', [
            'title' => 'دامون',
            'description' => 'سامانه مدیریت محتوا',
            'firstname' => 'احمدرضا',
            'lastname' => 'ابراهیمی',
            'username' => 'owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('filament.admin.auth.login'));

        $user = User::query()->where('username', 'owner')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->owner());
        $this->assertTrue(Setting::installed());
        $this->assertSame('دامون', Setting::current()?->title);
        $this->assertTrue(Auth::attempt([
            'username' => 'owner',
            'password' => 'password123',
        ]));

        $this->get('/install')->assertRedirect(route('filament.admin.auth.login'));
    }
}
