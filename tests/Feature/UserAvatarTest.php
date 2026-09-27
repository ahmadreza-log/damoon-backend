<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UserAvatarTest extends TestCase
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

    public function test_the_avatar_field_is_first_on_the_user_form(): void
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

        $create = Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateUser::class)
            ->assertFormFieldExists('avatar');

        $this->assertSame('avatar', array_key_first($create->instance()->form->getFlatFields()));

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/users/'.$owner->getKey().'/edit')
            ->assertOk();

        $edit = Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditUser::class, ['record' => $owner->getKey()])
            ->assertFormFieldExists('avatar');

        $this->assertSame('avatar', array_key_first($edit->instance()->form->getFlatFields()));

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/customers/create')
            ->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateCustomer::class)
            ->assertFormFieldDoesNotExist('avatar');
    }

    public function test_create_stores_the_avatar_and_a_replacement_drops_the_old_file(): void
    {
        $disk = Storage::fake('public');

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
                'confirmation' => 'password123',
                'avatar' => $this->picture('face.png', 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staff = User::query()->where('username', 'sara_staff')->first();

        $this->assertNotNull($staff);
        $this->assertIsString($staff->avatar);
        $this->assertStringStartsWith('avatars/', $staff->avatar);
        $disk->assertExists($staff->avatar);
        $this->assertStringContainsString($staff->avatar, (string) $staff->getFilamentAvatarUrl());

        $blank = User::factory()->create([
            'username' => 'no_avatar',
            'email' => 'no.avatar@example.com',
            'phone' => '09120000004',
        ]);

        $this->assertNull($blank->avatar);
        $this->assertStringContainsString('images/default-avatar.png', (string) $blank->getFilamentAvatarUrl());

        $previous = $staff->avatar;
        $disk->put('avatars/next.png', 'next');

        $staff->update(['avatar' => 'avatars/next.png']);

        $disk->assertMissing($previous);
        $disk->assertExists('avatars/next.png');

        $staff->delete();

        $disk->assertMissing('avatars/next.png');
    }

    /**
     * A one-pixel image. The test PHP build has no GD extension.
     */
    private function picture(string $name, string $encoded): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode($encoded));
    }
}
