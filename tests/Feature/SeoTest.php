<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Filament\Fields\MediaPicker;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Article;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Library;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Rankbeam\Seo\Models\SEOMeta;
use Tests\TestCase;

/**
 * Covers the SEO box from rankbeam/laravel-seo-filament on articles and pages.
 *
 * The tests check the panel's changes to the package: the social image is a media
 * library picture stored as /storage/{path}, title and description stop at the
 * seo_meta column lengths, empty values fall back to the body and cover, the media
 * page lists and clears social images, and the box speaks Persian.
 *
 * Extending:
 * - A new model with the SEO box needs a save and fallback test here.
 * - Tests that store files call Storage::fake('public') first so nothing reaches the real disk.
 */
class SeoTest extends TestCase
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
     * The edit form loads the saved SEO row, keeps it on save, and clears an emptied field.
     *
     * The social image field is the media picker holding the plain library path, and
     * the stored value is /storage/{path}. A record without SEO gets no seo_meta row.
     */
    public function test_the_seo_box_loads_and_saves_the_seo_row(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Seo::FOLDER, $this->picture('share.png'), 'share.png');
        $owner = $this->owner();
        $page = Page::query()->create(['title' => 'درباره ما', 'content' => '<p>درباره</p>', 'author_id' => $owner->getKey()]);
        $page->seoMeta()->create([
            'locale' => 'fa',
            'title' => 'درباره دامون',
            'description' => 'معرفی دامون',
            'og_image' => '/storage/'.Seo::FOLDER.'/share.png',
        ]);
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/pages/'.$page->getKey().'/edit')
            ->assertOk()
            ->assertSee('عنوان سئو')
            ->assertSee('تصویر اشتراک‌گذاری')
            ->assertSee('پیش‌نمایش نتیجهٔ جستجو');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditPage::class, ['record' => $page->getKey()])
            ->assertFormFieldExists('seo_meta.og_image', fn (MediaPicker $field): bool => $field->getDirectory() === Seo::FOLDER)
            ->assertFormSet([
                'seo_meta.title' => 'درباره دامون',
                'seo_meta.description' => 'معرفی دامون',
                'seo_meta.og_image' => Seo::FOLDER.'/share.png',
            ])
            ->fillForm(['seo_meta.description' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $meta = $page->refresh()->seoMeta;
        $this->assertSame('درباره دامون', $meta->title);
        $this->assertNull($meta->description);
        $this->assertSame('/storage/'.Seo::FOLDER.'/share.png', $meta->og_image);
        $this->assertSame(1, SEOMeta::query()->count());

        $plain = Article::query()->create(['title' => 'بدون سئو', 'content' => '<p>متن</p>', 'author_id' => $owner->getKey(), 'published_at' => now()]);
        $this->assertFalse($plain->seoMeta()->exists());

        $page->delete();
        $this->assertSame(0, SEOMeta::query()->count());
    }

    /**
     * Title and description longer than the seo_meta columns are refused with a form error.
     */
    public function test_seo_text_longer_than_the_columns_is_refused(): void
    {
        $owner = $this->owner();
        $article = Article::query()->create(['title' => 'نوشته', 'content' => '<p>متن</p>', 'author_id' => $owner->getKey(), 'published_at' => now()]);
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/'.$article->getKey().'/edit')
            ->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditArticle::class, ['record' => $article->getKey()])
            ->fillForm([
                'seo_meta.title' => str_repeat('ا', Seo::TITLE + 1),
                'seo_meta.description' => str_repeat('ب', Seo::DESCRIPTION + 1),
            ])
            ->call('save')
            ->assertHasFormErrors(['seo_meta.title' => 'max', 'seo_meta.description' => 'max']);

        $this->assertFalse($article->seoMeta()->exists());
    }

    /**
     * With the box left empty, SEO falls back to the body text, the cover, and the site address.
     */
    public function test_empty_seo_falls_back_to_the_content(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        $article = Article::query()->create([
            'title' => 'نهال گردو',
            'content' => '<p>کاشت نهال</p><p>در پاییز</p>',
            'cover' => 'articles/covers/cover.png',
            'author_id' => $owner->getKey(),
            'published_at' => now(),
        ]);
        $page = Page::query()->create(['title' => 'تماس', 'slug' => 'تماس', 'content' => null, 'author_id' => $owner->getKey()]);

        $this->assertSame('نهال گردو', $article->getSEOTitle());
        $this->assertSame('کاشت نهال در پاییز', $article->getSEODescription());
        $this->assertSame('/storage/articles/covers/cover.png', $article->getSEOImage());
        $this->assertSame(url('articles/'.$article->slug), $article->getUrlForSEO());
        $this->assertNull($page->getSEODescription());
        $this->assertSame(url('تماس'), $page->getUrlForSEO());
    }

    /**
     * The media page names social images and deleting one clears it from the SEO row.
     */
    public function test_social_images_show_their_use_in_the_library(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Seo::FOLDER, $this->picture('share.png'), 'share.png');
        $owner = $this->owner();
        $page = Page::query()->create(['title' => 'خدمات', 'content' => '<p>خدمات</p>', 'author_id' => $owner->getKey()]);
        $page->seoMeta()->create(['locale' => 'fa', 'og_image' => '/storage/'.Seo::FOLDER.'/share.png']);

        $row = collect(Library::rows())->firstWhere('path', Seo::FOLDER.'/share.png');
        $this->assertSame('تصویر سئو', $row['place']);
        $this->assertSame('تصویر اشتراک‌گذاری برگه خدمات', $row['usage']);

        Library::drop(Seo::FOLDER.'/share.png');

        $this->assertNull($page->seoMeta()->first()->og_image);
        $disk->assertMissing(Seo::FOLDER.'/share.png');
    }

    /**
     * The box labels and live warnings are Persian in the panel.
     */
    public function test_the_seo_box_is_persian(): void
    {
        app()->setLocale('fa');

        $this->assertSame('سئو', __('seo-filament::seo-filament.section.title'));
        $this->assertSame('عنوان سئو', __('seo-filament::seo-filament.fields.title'));
        $this->assertSame('12 / 60 نویسه', __('seo-filament::seo-filament.fields.counter', ['length' => 12, 'max' => 60]));
        $this->assertStringContainsString('عنوان سئو خالی است', __('seo::seo.warnings.title_is_fallback'));
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
     * A one-pixel image. The test PHP build may have no GD extension.
     */
    private function picture(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );
    }
}
