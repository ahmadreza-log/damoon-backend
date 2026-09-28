<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Setting;
use App\Models\User;
use App\Support\Library;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the staff avatar: where the field sits and how its file is kept.
 *
 * The avatar is a round MediaPicker at the top of the user form. Customers have no
 * avatar. Files picked or uploaded for an avatar stay in the media library even
 * when the avatar changes or the user is deleted.
 *
 * Extending:
 * - The field is built in Fields::avatar(); test a change to its options here.
 */
class UserAvatarTest extends TestCase
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
     * The avatar is the first field on the user create and edit forms and absent from the customer form.
     */
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

    /**
     * An avatar uploaded through the picker is saved under avatars/ and never deleted by the user record.
     *
     * The new user's avatar URL points at the stored file. A user without an avatar
     * shows the default avatar image. Changing the avatar keeps the old file, which the
     * library still labels as an avatar, and deleting the user keeps the current file.
     */
    public function test_an_avatar_uploaded_in_the_picker_is_stored_and_stays_in_the_library(): void
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
            ->callAction(TestAction::make('pick')->schemaComponent('avatar'), data: [
                'files' => $this->picture('face.png', 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
            ])
            ->assertHasNoFormErrors()
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

        $disk->assertExists($previous);
        $disk->assertExists('avatars/next.png');
        $this->assertSame('آواتار', collect(Library::rows())->firstWhere('path', $previous)['place']);

        $staff->delete();

        $disk->assertExists('avatars/next.png');
    }

    /**
     * A fake upload built from base64 image bytes, so no GD extension is needed to create it.
     */
    private function picture(string $name, string $encoded): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode($encoded));
    }
}
