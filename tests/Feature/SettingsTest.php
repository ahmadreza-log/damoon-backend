<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Pages\SiteSettings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the settings group in the sidebar and the general settings page.
 *
 * Extending:
 * - A new page in the settings group gets an assertSee on the sidebar and its own access check.
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Marks the site as installed and creates the owner, so the users under test are ordinary staff.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->create([
            'title' => 'دامون',
            'description' => 'سامانه مدیریت محتوا',
            'installed_at' => now(),
        ]);

        User::factory()->create(['username' => 'owner_user', 'email' => 'owner@example.com', 'phone' => '09120000001']);
    }

    /**
     * The settings group holds the general and form settings, last in the sidebar.
     */
    public function test_the_sidebar_has_a_settings_group(): void
    {
        $admin = $this->staff([Section::HOME, Section::FORMS, Section::SETTINGS]);

        $html = $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($admin))
            ->get('/admin')
            ->assertOk()
            ->assertSee('تنظیمات عمومی')
            ->assertSee('تنظیمات فرم‌ها')
            ->getContent();

        $this->assertMatchesRegularExpression('/فرم‌ها.*fi-sidebar-group-label[^>]*>\s*تنظیمات\s*</su', (string) $html);
    }

    /**
     * The settings section opens the page and saves the title and description; others get 403.
     */
    public function test_general_settings_save_the_site_title(): void
    {
        $admin = $this->staff([Section::SETTINGS]);
        $token = $this->token($admin);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('عنوان سایت')
            ->assertSee('دامون');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(SiteSettings::class)
            ->fillForm(['title' => '', 'description' => ''])
            ->call('save')
            ->assertHasFormErrors(['title' => 'required', 'description' => 'required']);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(SiteSettings::class)
            ->fillForm(['title' => '  هایپر صنعت دامون ', 'description' => 'تولیدکننده تجهیزات صنعتی'])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = Setting::current();
        $this->assertSame('هایپر صنعت دامون', $settings?->title);
        $this->assertSame('تولیدکننده تجهیزات صنعتی', $settings?->description);
        $this->assertNotNull($settings?->installed_at);
        $this->assertSame('هایپر صنعت دامون', Setting::brand());

        $member = $this->staff([Section::HOME], ['username' => 'member_user', 'email' => 'member@example.com', 'phone' => '09120000005']);

        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($member))
            ->get('/admin/settings')
            ->assertForbidden();
    }

    /**
     * A staff account with the given sections.
     *
     * @param  list<string>  $sections
     * @param  array<string, mixed>  $attributes
     */
    private function staff(array $sections, array $attributes = []): User
    {
        $user = User::factory()->create([
            'username' => 'staff_user',
            'email' => 'staff@example.com',
            'phone' => '09120000004',
            ...$attributes,
        ]);
        $user->grant($sections);

        return $user;
    }

    /**
     * A panel session token for the given user.
     */
    private function token(User $user): string
    {
        return app(AccessTokens::class)->issue($user, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);
    }
}
