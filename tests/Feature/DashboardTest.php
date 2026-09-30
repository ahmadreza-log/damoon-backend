<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Models\Comment;
use App\Models\Entry;
use App\Models\Form;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the dashboard: the welcome banner and the figures under it.
 *
 * Extending:
 * - A new figure or shortcut gets an assertSee for a user who may open it and an assertDontSee for one who may not.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Marks the site as installed, so panel pages do not redirect to /install.
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
     * The banner greets the user by name, and only figures and shortcuts for their sections appear.
     */
    public function test_the_dashboard_shows_what_the_user_may_open(): void
    {
        $form = Form::factory()->create();
        Entry::factory()->for($form)->count(2)->create();
        Entry::factory()->for($form)->read()->create();
        Comment::factory()->count(3)->create();
        Comment::factory()->approved()->create();
        User::factory()->create(['username' => 'owner_user', 'email' => 'owner@example.com', 'phone' => '09120000001']);
        $staff = User::factory()->create([
            'username' => 'support_user',
            'email' => 'support@example.com',
            'phone' => '09120000004',
            'firstname' => 'نوروز',
            'lastname' => 'کاظمی',
        ]);
        $staff->grant([Section::HOME, Section::INBOX, Section::COMMENTS]);

        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($staff))
            ->get('/admin')
            ->assertOk()
            ->assertSee('بخیر، نوروز کاظمی')
            ->assertSee('به پنل مدیریت دامون خوش آمدید.')
            ->assertSee('صندوق پیام‌ها')
            ->assertSee('پیام‌های تازه')
            ->assertSee('۳ پیام در کل')
            ->assertSee('دیدگاه‌های در انتظار')
            ->assertSee('۱ دیدگاه تأییدشده')
            ->assertDontSee('نوشته تازه')
            ->assertDontSee('نوشته‌های منتشرشده')
            ->assertDontSee('نفر در دو هفته اخیر');
    }

    /**
     * With the home section alone, the banner shows without shortcuts and the figures hide.
     */
    public function test_home_alone_shows_only_the_banner(): void
    {
        User::factory()->create(['username' => 'owner_user', 'email' => 'owner@example.com', 'phone' => '09120000001']);
        $staff = User::factory()->create(['username' => 'home_user', 'email' => 'home@example.com', 'phone' => '09120000005']);
        $staff->grant([Section::HOME]);

        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($staff))
            ->get('/admin')
            ->assertOk()
            ->assertSee('به پنل مدیریت دامون خوش آمدید.')
            ->assertDontSee('میان‌برها')
            ->assertDontSee('پیام‌های تازه')
            ->assertDontSee('fi-wi-stats-overview-stat', false);
    }

    /**
     * A panel session token for the given user.
     */
    private function token(User $user): string
    {
        return app(AccessTokens::class)->issue($user, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);
    }
}
