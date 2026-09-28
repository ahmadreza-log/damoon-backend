<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Degree;
use App\Models\Gender;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the staff profile fields on the user form.
 *
 * Staff have a personnel code, national ID, job title, degree, field of study, and
 * gender. The tests check that the fields and their Persian options appear, that
 * create and edit store them, and that an invalid national ID is refused.
 *
 * Extending:
 * - The fields are built in Fields::staff(); the national ID check is App\Rules\National.
 */
class UserStaffProfileTest extends TestCase
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
     * The create form shows every profile field and option, and create and edit save them.
     */
    public function test_create_and_edit_store_the_staff_profile(): void
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
            ->assertSee('کد پرسنلی')
            ->assertSee('کد ملی')
            ->assertSee('موقعیت شغلی')
            ->assertSee('مدرک تحصیلی')
            ->assertSee('رشته تحصیلی')
            ->assertSee('لیسانس')
            ->assertSee('دکتری')
            ->assertSee('جنسیت')
            ->assertSee('خانم')
            ->assertSee('آقا');

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
                'personnel' => 'P-100',
                'national' => '0013542419',
                'job' => 'کارشناس فروش',
                'degree' => Degree::BACHELOR,
                'major' => 'مهندسی نرم‌افزار',
                'gender' => Gender::WOMAN,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staff = User::query()->where('username', 'sara_staff')->first();

        $this->assertNotNull($staff);
        $this->assertSame('P-100', $staff->personnel);
        $this->assertSame('0013542419', $staff->national);
        $this->assertSame('کارشناس فروش', $staff->job);
        $this->assertSame(Degree::BACHELOR, $staff->degree);
        $this->assertSame('مهندسی نرم‌افزار', $staff->major);
        $this->assertSame(Gender::WOMAN, $staff->gender);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditUser::class, ['record' => $staff->getKey()])
            ->fillForm([
                'personnel' => 'P-200',
                'gender' => Gender::MAN,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $staff->refresh();

        $this->assertSame('P-200', $staff->personnel);
        $this->assertSame(Gender::MAN, $staff->gender);
    }

    /**
     * A national ID with a wrong check digit fails validation and no user is created.
     *
     * 0013542419 is valid; changing only its last digit to 0 must be refused.
     */
    public function test_an_invalid_national_id_is_rejected(): void
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
                'confirmation' => 'password123',
                'national' => '0013542410',
            ])
            ->call('create')
            ->assertHasFormErrors(['national']);

        $this->assertNull(User::query()->where('username', 'sara_staff')->first());
    }
}
