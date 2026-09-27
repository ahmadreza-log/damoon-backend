<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class InstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_redirects_to_install_until_setup_is_complete(): void
    {
        $this->get('/admin')->assertRedirect(route('install'));

        $this->get('/install')
            ->assertOk()
            ->assertSee('نصب سامانه')
            ->assertSee('عنوان');
    }

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
        $this->assertTrue($user->isOwner());
        $this->assertTrue(Setting::isInstalled());
        $this->assertSame('دامون', Setting::current()?->title);
        $this->assertTrue(Auth::attempt([
            'username' => 'owner',
            'password' => 'password123',
        ]));

        $this->get('/install')->assertRedirect(route('filament.admin.auth.login'));
    }
}
