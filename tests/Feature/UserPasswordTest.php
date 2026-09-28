<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the password box on the staff user form.
 *
 * Creating a user asks for a new password twice. Editing a user asks for the current
 * password as well, and only changes the password when the current one matches.
 * Leaving the box empty on edit keeps the old password.
 *
 * Extending:
 * - The box is built in Fields::password(); its checks are Fields::known(), length(), and agree().
 */
class UserPasswordTest extends TestCase
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
     * The password fields appear where expected on each form.
     *
     * Create shows new password and repeat but no current password. Edit shows current,
     * new, and repeat, in that order, before section access. The customer form has a
     * single password field and no current password.
     */
    public function test_the_password_box_sits_before_section_access(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/users/create')
            ->assertOk()
            ->assertSee('رمز عبور جدید')
            ->assertSee('تکرار رمز عبور')
            ->assertDontSee('رمز عبور فعلی');

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/users/'.$owner->getKey().'/edit')
            ->assertOk()
            ->assertSeeInOrder([
                'رمز عبور فعلی',
                'رمز عبور جدید',
                'تکرار رمز عبور',
                'دسترسی بخش‌ها',
            ]);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/customers/create')
            ->assertOk()
            ->assertSee('رمز عبور')
            ->assertDontSee('رمز عبور فعلی');
    }

    /**
     * Create refuses a repeat that does not match, and stores a matching password hashed.
     */
    public function test_create_stores_a_repeated_new_password(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/users/create')
            ->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateUser::class)
            ->fillForm([
                'firstname' => 'سارا',
                'lastname' => 'کریمی',
                'username' => 'sara_staff',
                'email' => 'sara@example.com',
                'phone' => '09120000003',
                'password' => 'password123',
                'confirmation' => 'different1',
            ])
            ->call('create')
            ->assertHasFormErrors(['confirmation']);

        $this->assertNull(User::query()->where('username', 'sara_staff')->first());

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateUser::class)
            ->fillForm([
                'firstname' => 'سارا',
                'lastname' => 'کریمی',
                'username' => 'sara_staff',
                'email' => 'sara@example.com',
                'phone' => '09120000003',
                'password' => 'password123',
                'confirmation' => 'password123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staff = User::query()->where('username', 'sara_staff')->first();

        $this->assertNotNull($staff);
        $this->assertTrue(Hash::check('password123', $staff->password));
    }

    /**
     * Edit changes the password only when the current password is right.
     *
     * A wrong current password fails validation and keeps the old password. Saving
     * with the password box empty also keeps it. The right current password with a
     * matching new password replaces it.
     */
    public function test_edit_changes_the_password_only_when_the_previous_one_matches(): void
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
            'password' => 'secret-pass',
        ]);
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/users/'.$member->getKey().'/edit')
            ->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditUser::class, ['record' => $member->getKey()])
            ->fillForm([
                'current' => 'wrong-pass',
                'password' => 'secret-new1',
                'confirmation' => 'secret-new1',
            ])
            ->call('save')
            ->assertHasFormErrors(['current']);

        $member->refresh();
        $this->assertTrue(Hash::check('secret-pass', $member->password));

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditUser::class, ['record' => $member->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();
        $this->assertTrue(Hash::check('secret-pass', $member->password));

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditUser::class, ['record' => $member->getKey()])
            ->fillForm([
                'current' => 'secret-pass',
                'password' => 'secret-new1',
                'confirmation' => 'secret-new1',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();
        $this->assertTrue(Hash::check('secret-new1', $member->password));
    }
}
