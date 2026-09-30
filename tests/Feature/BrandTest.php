<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Resources\Brands\Pages\CreateBrand;
use App\Filament\Resources\Brands\Pages\EditBrand;
use App\Models\Brand;
use App\Models\Setting;
use App\Models\User;
use App\Support\Library;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the brands section in the panel, the Brand model, and the brand routes of the content API.
 *
 * Extending:
 * - A new brand field needs an assertFormFieldExists, a value in the create test, and a key in the API test.
 * - Tests that store files call Storage::fake('public') first so nothing reaches the real disk.
 */
class BrandTest extends TestCase
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
     * A brand is created with every field, edited, listed, and deleted without losing its files.
     *
     * A member needs the brands section to open the list.
     */
    public function test_a_brand_can_be_created_edited_and_deleted(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Brand::LOGOS, $this->picture('logo.png'), 'logo.png');
        $owner = $this->owner();
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/brands/create')
            ->assertOk()
            ->assertSee('عنوان')
            ->assertSee('نامک')
            ->assertSee('عنوان انگلیسی')
            ->assertSee('توضیحات')
            ->assertSee('ویژگی‌های برند')
            ->assertSee('وبسایت')
            ->assertSee('لینکدین')
            ->assertSee('کاتالوگ')
            ->assertSee('لینک دانلود نرم‌افزار')
            ->assertSee('لوگوی برند')
            ->assertSee('سئو');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateBrand::class)
            ->assertFormFieldExists('title')
            ->assertFormFieldExists('slug')
            ->assertFormFieldExists('english')
            ->assertFormFieldExists('content')
            ->assertFormFieldExists('features')
            ->assertFormFieldExists('website')
            ->assertFormFieldExists('linkedin')
            ->assertFormFieldExists('catalog')
            ->assertFormFieldExists('download')
            ->assertFormFieldExists('logo')
            ->assertFormFieldExists('seo_meta.title')
            ->assertFormFieldExists('seo_meta.description')
            ->assertFormFieldExists('seo_meta.og_image')
            ->fillForm([
                'title' => 'داده‌پردازان پارس',
                'english' => 'Pars Data',
                'content' => '<p>تولیدکنندهٔ نرم‌افزار</p>',
                'features' => [
                    ['title' => 'پشتیبانی شبانه‌روزی', 'description' => 'همهٔ روزهای هفته'],
                    ['title' => 'گارانتی', 'description' => ''],
                ],
                'website' => 'https://parsdata.com',
                'linkedin' => 'https://www.linkedin.com/company/parsdata',
                'download' => 'https://parsdata.com/download',
                'catalog' => [UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf')],
                'logo' => Brand::LOGOS.'/logo.png',
                'seo_meta' => ['title' => 'پارس دیتا', 'description' => 'برند نرم‌افزاری'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $brand = Brand::query()->firstOrFail();

        $this->assertSame('داده‌پردازان-پارس', $brand->slug);
        $this->assertSame('Pars Data', $brand->english);
        $this->assertStringContainsString('تولیدکنندهٔ نرم‌افزار', $brand->html());
        $this->assertSame('پشتیبانی شبانه‌روزی', $brand->features[0]['title']);
        $this->assertSame('https://parsdata.com', $brand->website);
        $this->assertSame('https://www.linkedin.com/company/parsdata', $brand->linkedin);
        $this->assertSame('https://parsdata.com/download', $brand->download);
        $this->assertSame(Brand::LOGOS.'/logo.png', $brand->logo);
        $this->assertStringStartsWith(Brand::CATALOGS.'/', (string) $brand->catalog);
        $disk->assertExists((string) $brand->catalog);
        $this->assertSame('پارس دیتا', $brand->seoMeta->title);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditBrand::class, ['record' => $brand->getKey()])
            ->fillForm(['title' => 'پارس دیتا'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('پارس دیتا', $brand->refresh()->title);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/brands')
            ->assertOk()
            ->assertSee('پارس دیتا')
            ->assertSee('Pars Data');

        $catalog = (string) $brand->catalog;

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditBrand::class, ['record' => $brand->getKey()])
            ->callAction('delete');

        $this->assertNull(Brand::query()->find($brand->getKey()));
        $disk->assertExists(Brand::LOGOS.'/logo.png');
        $disk->assertExists($catalog);

        $editor = User::factory()->create(['username' => 'editor_user', 'email' => 'editor@example.com', 'phone' => '09120000003']);
        $editor->grant([Section::BRANDS]);
        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($editor))->get('/admin/brands')->assertOk();

        $member = User::factory()->create(['username' => 'member_user', 'email' => 'member@example.com', 'phone' => '09120000002']);
        $member->grant([Section::ARTICLES]);
        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($member))->get('/admin/brands')->assertForbidden();
    }

    /**
     * The API lists brands by title a page at a time, searches both titles, and sends one brand with its details.
     */
    public function test_brand_api_lists_and_shows_brands(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Brand::LOGOS, $this->picture('logo.png'), 'logo.png');
        $disk->put(Brand::CATALOGS.'/guide.pdf', '%PDF-1.4');

        $brand = Brand::factory()->create([
            'title' => 'آرتا صنعت',
            'english' => 'Arta Industry',
            'content' => '<p>درباره آرتا</p>',
            'features' => [['title' => 'کیفیت', 'description' => 'استاندارد جهانی'], ['title' => '', 'description' => 'بی‌عنوان']],
            'logo' => Brand::LOGOS.'/logo.png',
            'catalog' => Brand::CATALOGS.'/guide.pdf',
        ]);
        Brand::factory()->create(['title' => 'بهار', 'english' => 'Bahar']);
        Brand::factory()->create(['title' => 'پارس', 'english' => 'Pars']);

        $this->get('/v1/brands')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', 'آرتا صنعت')
            ->assertJsonPath('data.0.english', 'Arta Industry')
            ->assertJsonPath('data.0.logo.path', Brand::LOGOS.'/logo.png')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonMissingPath('data.0.features');
        $this->get('/v1/brands?per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data');
        $this->get('/v1/brands?q=bahar')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'بهار');
        $this->get('/v1/brands?per_page=51')->assertStatus(422)->assertJsonValidationErrors('per_page');

        $response = $this->get('/v1/brands/'.urlencode((string) $brand->slug))
            ->assertOk()
            ->assertJsonPath('data.english', 'Arta Industry')
            ->assertJsonPath('data.features', [['title' => 'کیفیت', 'description' => 'استاندارد جهانی']])
            ->assertJsonPath('data.catalog.path', Brand::CATALOGS.'/guide.pdf')
            ->assertJsonPath('data.catalog.name', 'guide.pdf')
            ->assertJsonPath('data.content.content.0.type', 'paragraph');

        $this->assertStringStartsWith('آرتا صنعت', (string) $response->json('data.seo.title'));
        $this->assertSame(url('/storage/'.Brand::CATALOGS.'/guide.pdf'), $response->json('data.catalog.url'));
        $this->assertSame(url('/brands/'.$brand->slug), $response->json('data.url'));
        $this->assertStringContainsString('https://', (string) $response->json('data.website'));

        $this->get('/v1/brands/missing')->assertNotFound()->assertJsonPath('message', 'برند پیدا نشد.');
    }

    /**
     * The media page shows who uses a brand file, and deleting the file there clears it from the brand.
     */
    public function test_deleting_a_library_file_clears_it_from_brands(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Brand::LOGOS, $this->picture('logo.png'), 'logo.png');
        $disk->put(Brand::CATALOGS.'/guide.pdf', '%PDF-1.4');
        $brand = Brand::factory()->create([
            'title' => 'نیک‌ارتباط',
            'logo' => Brand::LOGOS.'/logo.png',
            'catalog' => Brand::CATALOGS.'/guide.pdf',
        ]);

        $usage = collect(Library::rows())->pluck('usage', 'path');
        $this->assertSame('لوگوی برند نیک‌ارتباط', $usage[Brand::LOGOS.'/logo.png']);
        $this->assertSame('کاتالوگ برند نیک‌ارتباط', $usage[Brand::CATALOGS.'/guide.pdf']);

        Library::drop(Brand::LOGOS.'/logo.png');
        Library::drop(Brand::CATALOGS.'/guide.pdf');

        $brand->refresh();
        $this->assertNull($brand->logo);
        $this->assertNull($brand->catalog);
        $disk->assertMissing(Brand::CATALOGS.'/guide.pdf');
    }

    /**
     * The first user, who becomes the owner.
     */
    private function owner(): User
    {
        return User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
    }

    /**
     * A panel token for the given user.
     */
    private function token(User $user): string
    {
        return app(AccessTokens::class)->issue($user, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);
    }

    /**
     * A one-pixel PNG upload.
     */
    private function picture(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );
    }
}
