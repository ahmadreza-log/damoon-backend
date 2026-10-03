<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Pages\SocialSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Icons;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the social networks settings page, its icon picker, and /v1/socials.
 *
 * Extending:
 * - A new field on each link gets a value in the save test and a key in the API test.
 */
class SocialSettingsTest extends TestCase
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
     * The page sits in the settings group between the general and form settings, and needs its own section.
     */
    public function test_the_page_needs_the_socials_section(): void
    {
        $admin = $this->staff([Section::HOME, Section::SETTINGS, Section::SOCIALS, Section::FORMS]);

        $html = $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($admin))
            ->get('/admin/settings/socials')
            ->assertOk()
            ->assertSee('تنظیمات شبکه‌های اجتماعی')
            ->assertSee('لینک شبکه‌های اجتماعی')
            ->assertSee('افزودن شبکهٔ اجتماعی')
            ->getContent();

        $this->assertMatchesRegularExpression('/تنظیمات عمومی.*تنظیمات شبکه‌های اجتماعی.*تنظیمات فرم‌ها/su', (string) $html);

        $member = $this->staff([Section::HOME, Section::SETTINGS], ['username' => 'member_user', 'email' => 'member@example.com', 'phone' => '09120000005']);

        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($member))
            ->get('/admin/settings/socials')
            ->assertForbidden();
    }

    /**
     * The repeater saves name, icon, and link in order, and the page shows them again.
     */
    public function test_the_links_are_saved_in_order(): void
    {
        $token = $this->token($this->staff([Section::SOCIALS]));
        $this->withCookie((string) config('sanctum.panel_cookie'), $token)->get('/admin/settings/socials')->assertOk();

        $undo = Repeater::fake();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(SocialSettings::class)
            ->fillForm(['socials' => [
                ['name' => ' تلگرام ', 'icon' => 'si-telegram', 'url' => 'https://t.me/damoon'],
                ['name' => 'ایتا', 'icon' => 'brand-eitaa', 'url' => 'https://eitaa.com/damoon'],
                ['name' => 'جیمیل', 'icon' => 'si-gmail', 'url' => 'mailto:info@damoon.ir'],
                ['name' => 'تلفن', 'icon' => 'heroicon-o-phone', 'url' => 'tel:+982112345678'],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $undo();

        $this->assertSame([
            ['name' => 'تلگرام', 'icon' => 'si-telegram', 'url' => 'https://t.me/damoon'],
            ['name' => 'ایتا', 'icon' => 'brand-eitaa', 'url' => 'https://eitaa.com/damoon'],
            ['name' => 'جیمیل', 'icon' => 'si-gmail', 'url' => 'mailto:info@damoon.ir'],
            ['name' => 'تلفن', 'icon' => 'heroicon-o-phone', 'url' => 'tel:+982112345678'],
        ], Setting::current()?->socials);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/settings/socials')
            ->assertOk()
            ->assertSee('eitaa.com')
            ->assertSee('جیمیل');
    }

    /**
     * Each row needs a name, a known icon, and a web, email, or phone link.
     */
    public function test_each_link_is_validated(): void
    {
        $token = $this->token($this->staff([Section::SOCIALS]));
        $this->withCookie((string) config('sanctum.panel_cookie'), $token)->get('/admin/settings/socials')->assertOk();

        $undo = Repeater::fake();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(SocialSettings::class)
            ->fillForm(['socials' => [
                ['name' => '', 'icon' => 'si-not-a-real-icon', 'url' => 'javascript:alert(1)'],
                ['name' => 'سایت', 'icon' => 'heroicon-s-phone', 'url' => 'ftp://damoon.ir'],
            ]])
            ->call('save')
            ->assertHasFormErrors([
                'socials.0.name' => 'required',
                'socials.0.icon',
                'socials.0.url',
                'socials.1.icon',
                'socials.1.url',
            ]);

        $undo();

        $this->assertNull(Setting::current()?->socials);
    }

    /**
     * Search finds icons by English or Persian name, and every popular icon exists.
     */
    public function test_the_icon_picker_searches_every_set(): void
    {
        foreach (array_keys(Icons::POPULAR) as $name) {
            $this->assertTrue(Icons::exists($name), $name);
        }

        $this->assertArrayHasKey('si-instagram', Icons::search('Insta'));
        $this->assertArrayHasKey('brand-eitaa', Icons::search('ایتا'));
        $this->assertArrayHasKey('brand-bale', Icons::search('bale'));
        $this->assertArrayHasKey('heroicon-o-envelope', Icons::search('envelope'));
        $this->assertArrayNotHasKey('heroicon-s-envelope', Icons::search('envelope'));
        $this->assertLessThanOrEqual(Icons::LIMIT, count(Icons::search('a')));
        $this->assertStringContainsString('<svg', Icons::label('brand-linkedin'));
        $this->assertFalse(Icons::exists('si-not-a-real-icon'));
    }

    /**
     * /v1/socials lists valid links in order with their SVG, and skips broken rows.
     */
    public function test_the_api_lists_the_links(): void
    {
        $this->getJson('/v1/socials')->assertOk()->assertExactJson(['data' => []]);

        Setting::current()?->update(['socials' => [
            ['name' => 'اینستاگرام', 'icon' => 'si-instagram', 'url' => 'https://instagram.com/damoon'],
            ['name' => 'قدیمی', 'icon' => 'si-removed-icon', 'url' => 'https://example.com'],
            ['name' => 'بله', 'icon' => 'brand-bale', 'url' => 'https://ble.ir/damoon'],
        ]]);

        $data = $this->getJson('/v1/socials')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'اینستاگرام')
            ->assertJsonPath('data.0.icon', 'si-instagram')
            ->assertJsonPath('data.0.url', 'https://instagram.com/damoon')
            ->assertJsonPath('data.1.icon', 'brand-bale')
            ->json('data');

        $this->assertStringStartsWith('<svg', (string) $data[0]['svg']);
        $this->assertStringContainsString('currentColor', (string) $data[1]['svg']);
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
