<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Resources\ApiKeys\Pages\ManageApiKeys;
use App\Http\Middleware\RequireApiKey;
use App\Models\ApiKey;
use App\Models\Setting;
use App\Models\User;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the API settings page and the key check on every /v1 route.
 *
 * Tests\TestCase sends a key for http://localhost on every request; these tests drop or
 * replace that header to see what the API does without a key.
 *
 * Extending:
 * - A new rule on a key gets a refused and an accepted request here.
 */
class ApiKeyTest extends TestCase
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
     * No key, a wrong key, or a switched-off key gets 401 on reads and writes alike.
     */
    public function test_the_api_needs_an_active_key(): void
    {
        $this->withoutHeader(RequireApiKey::HEADER);

        $this->getJson('/v1/articles')->assertUnauthorized()->assertJsonPath('message', 'کلید API فرستاده نشده یا معتبر نیست.');
        $this->postJson('/v1/auth/login', ['login' => 'x', 'password' => 'y'])->assertUnauthorized();
        $this->postJson('/v1/forms/contact', [])->assertUnauthorized();
        $this->postJson('/v1/articles/hello/comments', [])->assertUnauthorized();
        $this->getJson('/v1/schema', [RequireApiKey::HEADER => 'dmk_wrong'])->assertUnauthorized();

        [$key, $plain] = ApiKey::issue(['name' => 'خاموش', 'origin' => 'https://site.test', 'active' => false]);

        $this->getJson('/v1/schema', [RequireApiKey::HEADER => $plain])->assertUnauthorized();

        $key->update(['active' => true]);

        $this->getJson('/v1/schema', [RequireApiKey::HEADER => $plain])->assertOk();
    }

    /**
     * A browser request must come from the key's origin; a server request (no Origin) passes on the key.
     */
    public function test_a_key_opens_only_its_origin(): void
    {
        [, $plain] = ApiKey::issue(['name' => 'سایت', 'origin' => 'https://Site.test:443/blog']);

        $this->assertDatabaseHas('api_keys', ['origin' => 'https://site.test']);

        $headers = [RequireApiKey::HEADER => $plain];

        $this->getJson('/v1/socials', $headers)->assertOk();
        $this->getJson('/v1/socials', [...$headers, 'Origin' => 'https://site.test'])->assertOk();
        $this->getJson('/v1/socials', [...$headers, 'Origin' => 'HTTPS://SITE.TEST:443'])->assertOk();
        $this->getJson('/v1/socials', [...$headers, 'Origin' => 'https://evil.test'])
            ->assertForbidden()
            ->assertJsonPath('message', 'این کلید API برای این دامنه تعریف نشده است.');
        $this->getJson('/v1/socials', [...$headers, 'Origin' => 'http://site.test'])->assertForbidden();
        $this->getJson('/v1/socials', [...$headers, 'Origin' => 'null'])->assertForbidden();
    }

    /**
     * Preflight requests carry no key, so CORS answers them before the key check.
     */
    public function test_preflight_is_answered_without_a_key(): void
    {
        $this->withoutHeader(RequireApiKey::HEADER);

        $this->call('OPTIONS', '/v1/articles', server: [
            'HTTP_ORIGIN' => 'https://site.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-api-key',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    /**
     * used_at is written on use, but not again within a few minutes.
     */
    public function test_use_is_stamped_sparingly(): void
    {
        [$key, $plain] = ApiKey::issue(['name' => 'سرور', 'origin' => 'https://site.test']);

        $this->assertNull($key->used_at);

        $this->getJson('/v1/schema', [RequireApiKey::HEADER => $plain])->assertOk();
        $first = $key->refresh()->used_at;
        $this->assertNotNull($first);

        $this->travel(1)->minutes();
        $this->getJson('/v1/schema', [RequireApiKey::HEADER => $plain])->assertOk();
        $this->assertTrue($first->equalTo($key->refresh()->used_at));

        $this->travel(10)->minutes();
        $this->getJson('/v1/schema', [RequireApiKey::HEADER => $plain])->assertOk();
        $this->assertTrue($key->refresh()->used_at->greaterThan($first));
    }

    /**
     * Origins are cut to scheme, host, and a non-default port; anything else is refused.
     */
    public function test_origins_are_normalized(): void
    {
        $this->assertSame('https://site.test', ApiKey::normalize(' https://SITE.test/path?q=1 '));
        $this->assertSame('http://localhost:3000', ApiKey::normalize('http://localhost:3000'));
        $this->assertSame('http://site.test', ApiKey::normalize('http://site.test:80'));
        $this->assertSame('https://site.test:8443', ApiKey::normalize('https://site.test:8443/'));
        $this->assertNull(ApiKey::normalize('site.test'));
        $this->assertNull(ApiKey::normalize('ftp://site.test'));
        $this->assertNull(ApiKey::normalize('https://user:pass@site.test'));
        $this->assertNull(ApiKey::normalize('null'));
    }

    /**
     * The page sits last in the settings group and needs its own section.
     */
    public function test_the_page_needs_the_api_section(): void
    {
        $admin = $this->staff([Section::HOME, Section::SETTINGS, Section::FORMS, Section::API]);

        $html = $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($admin))
            ->get('/admin/settings/api')
            ->assertOk()
            ->assertSee('تنظیمات API')
            ->assertSee('ساخت کلید API')
            ->assertSee('http://localhost')
            ->assertDontSee($this->apikey)
            ->getContent();

        $this->assertMatchesRegularExpression('/تنظیمات عمومی.*تنظیمات فرم‌ها.*تنظیمات API/su', (string) $html);

        $member = $this->staff([Section::HOME, Section::SETTINGS], ['username' => 'member_user', 'email' => 'member@example.com', 'phone' => '09120000005']);

        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($member))
            ->get('/admin/settings/api')
            ->assertForbidden();
    }

    /**
     * Creating a key stores only its hash, shows the plain key once, and the key opens the API.
     */
    public function test_a_key_is_created_and_shown_once(): void
    {
        $token = $this->token($this->staff([Section::API]));
        $this->withCookie((string) config('sanctum.panel_cookie'), $token)->get('/admin/settings/api')->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ManageApiKeys::class)
            ->callAction('create', data: ['name' => 'سایت نکست', 'origin' => 'example.com', 'active' => true])
            ->assertHasActionErrors(['origin']);

        $this->assertDatabaseMissing('api_keys', ['name' => 'سایت نکست']);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ManageApiKeys::class)
            ->callAction('create', data: ['name' => 'سایت نکست', 'origin' => 'http://localhost:3000/', 'active' => true])
            ->assertHasNoActionErrors();

        $key = ApiKey::query()->where('name', 'سایت نکست')->firstOrFail();
        $plain = $this->revealed('کلید API ساخته شد');

        $this->assertSame('http://localhost:3000', $key->origin);
        $this->assertSame(hash('sha256', $plain), $key->token);
        $this->assertSame(substr($plain, -4), $key->hint);
        $this->assertStringStartsWith(ApiKey::PREFIX, $plain);
        $this->assertArrayNotHasKey('token', $key->toArray());

        $this->getJson('/v1/socials', [RequireApiKey::HEADER => $plain, 'Origin' => 'http://localhost:3000'])->assertOk();
    }

    /**
     * Regenerating a key shuts the old secret out and shows the new one.
     */
    public function test_a_key_can_be_regenerated(): void
    {
        [$key, $old] = ApiKey::issue(['name' => 'سایت', 'origin' => 'https://site.test']);

        $token = $this->token($this->staff([Section::API]));
        $this->withCookie((string) config('sanctum.panel_cookie'), $token)->get('/admin/settings/api')->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ManageApiKeys::class)
            ->callTableAction('regenerate', $key);

        $new = $this->revealed('کلید تازه ساخته شد');

        $this->assertNotSame($old, $new);
        $this->getJson('/v1/schema', [RequireApiKey::HEADER => $old])->assertUnauthorized();
        $this->getJson('/v1/schema', [RequireApiKey::HEADER => $new])->assertOk();
    }

    /**
     * The plain key in the notification with this title, read the way Filament's notifications component reads it.
     */
    private function revealed(string $title): string
    {
        $component = new Notifications;
        $component->mount();

        $notification = $component->notifications->first(fn (Notification $item): bool => $item->getTitle() === $title);

        $this->assertNotNull($notification, 'No notification titled '.$title);
        $this->assertSame('persistent', $notification->getDuration());
        $this->assertSame(1, preg_match('/'.preg_quote(ApiKey::PREFIX, '/').'[A-Za-z0-9]{'.ApiKey::LENGTH.'}/', (string) $notification->getBody(), $match));

        return $match[0];
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
