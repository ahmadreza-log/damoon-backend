<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Auth\EditProfile;
use App\Models\Article;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the user menu, the profile page, and the edit profile page.
 *
 * Extending:
 * - A new user menu link gets an assertSee for a user whose sections allow it and an assertDontSee otherwise.
 * - A field added to EditProfile::EDITABLE gets a save check here; a locked one gets a "stays unchanged" check.
 */
class ProfileTest extends TestCase
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
     * The menu shows the card, the profile links, and only the shortcuts the user's sections allow.
     */
    public function test_the_user_menu_follows_the_users_sections(): void
    {
        $writer = $this->staff([Section::HOME, Section::ARTICLES], ['email' => 'writer@example.com', 'job' => 'نویسنده محتوا']);

        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($writer))
            ->get('/admin')
            ->assertOk()
            ->assertSee('dp-usercard', false)
            ->assertSee('writer@example.com')
            ->assertSee('نویسنده محتوا')
            ->assertSee('پروفایل من')
            ->assertSee('ویرایش پروفایل')
            ->assertSee('نوشته تازه')
            ->assertSee('نوشته‌های من')
            ->assertSee('/admin/profile/edit', false)
            ->assertDontSee('صندوق پیام‌ها');
    }

    /**
     * Anyone signed in opens their own profile, with their details, sections, and latest articles.
     */
    public function test_the_profile_page_shows_the_users_own_details(): void
    {
        $writer = $this->staff([Section::ARTICLES], ['firstname' => 'سارا', 'lastname' => 'محمدی', 'major' => 'روزنامه‌نگاری']);
        Article::factory()->create(['title' => 'راهنمای انتخاب پمپ صنعتی', 'author_id' => $writer->getKey(), 'published_at' => now()->subDay()]);
        $reader = $this->staff([], ['username' => 'reader_user', 'email' => 'reader@example.com', 'phone' => '09120000009']);

        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($writer))
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('پروفایل من')
            ->assertSee('سارا محمدی')
            ->assertSee('روزنامه‌نگاری')
            ->assertSee('دسترسی‌ها')
            ->assertSee('راهنمای انتخاب پمپ صنعتی')
            ->assertSee('همه نوشته‌های من');

        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($reader))
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('هنوز بخشی برای شما باز نشده است.')
            ->assertDontSee('نوشتن اولین نوشته');
    }

    /**
     * The edit page saves the user's own fields, ignores locked ones, and checks the current password.
     */
    public function test_the_edit_profile_page_saves_only_editable_fields(): void
    {
        $user = $this->staff([Section::HOME], ['personnel' => 'P-100', 'job' => 'کارشناس']);
        $token = $this->token($user);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/profile/edit')
            ->assertOk()
            ->assertSee('ویرایش پروفایل')
            ->assertSee('کد پرسنلی، کد ملی و موقعیت شغلی را فقط مدیر سامانه می‌تواند تغییر دهد.');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditProfile::class)
            ->fillForm([
                'firstname' => 'نگار',
                'major' => 'مهندسی مکانیک',
                'personnel' => 'P-999',
                'job' => 'مدیر کل',
            ])
            ->set('data.active', false)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin/profile');

        $user->refresh();
        $this->assertSame('نگار', $user->firstname);
        $this->assertSame('مهندسی مکانیک', $user->major);
        $this->assertSame('P-100', $user->personnel);
        $this->assertSame('کارشناس', $user->job);
        $this->assertTrue((bool) $user->active);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditProfile::class)
            ->fillForm(['current' => 'wrong-password', 'password' => 'new-secret-1', 'confirmation' => 'new-secret-1'])
            ->call('save')
            ->assertHasFormErrors(['current']);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditProfile::class)
            ->fillForm(['current' => 'password', 'password' => 'new-secret-1', 'confirmation' => 'new-secret-1'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('new-secret-1', $user->refresh()->password));
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
