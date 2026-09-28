<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Filament\Fields\MediaGrid;
use App\Filament\Fields\MediaPicker;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Models\Article;
use App\Models\Setting;
use App\Models\User;
use App\Support\Library;
use App\Support\Sizes;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers MediaPicker, the WordPress-style image field used by every image input.
 *
 * The field opens one popup with two tabs: the media library to choose existing
 * pictures, and an upload tab for new files. The tests use the article cover
 * (single) and gallery (multiple) fields to check choosing, uploading, reordering,
 * removing, and the rule that a field only accepts pictures that are in the library.
 *
 * Extending:
 * - Test a new picker option here with the pick, remove, and reorder actions, addressed through schemaComponent().
 */
class MediaPickerTest extends TestCase
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
     * One popup both picks library pictures and uploads new ones.
     *
     * The library tab lists only pictures, so the PDF on the disk must not appear.
     * Picking one library picture and uploading one new file fills the gallery with
     * both, in that order; the upload is stored in the field's directory, gets its
     * WebP sizes, and shows up in the media library as a gallery picture. Reordering
     * ignores paths that are not in the field. Removing a picture only takes it out of
     * the field; the file stays on disk. The single cover field replaces its value on
     * each pick. After saving, the library reports where the cover picture is used.
     */
    public function test_the_gallery_takes_library_pictures_and_new_uploads_in_one_popup(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs('media', UploadedFile::fake()->image('old.jpg', 900, 600), 'old.jpg');
        $disk->putFileAs('media', UploadedFile::fake()->image('other.png', 400, 400), 'other.png');
        $disk->put('media/notes.pdf', '%PDF-1.4');
        $owner = $this->owner();
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/create')
            ->assertOk()
            ->assertSee('برای انتخاب از رسانه‌ها یا بارگذاری تصویر کلیک کنید');

        $this->assertEqualsCanonicalizing(['media/other.png', 'media/old.jpg'], array_column(MediaGrid::make('chosen')->tiles(), 'path'));

        $page = Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateArticle::class)
            ->assertFormFieldExists('cover', fn (MediaPicker $field): bool => ! $field->isMultiple() && $field->getDirectory() === 'articles/covers')
            ->assertFormFieldExists('gallery', fn (MediaPicker $field): bool => $field->isMultiple() && $field->getDirectory() === 'articles/gallery')
            ->mountAction(TestAction::make('pick')->schemaComponent('gallery'))
            ->assertMountedActionModalSee(['کتابخانهٔ رسانه', 'بارگذاری فایل', 'old.jpg', 'other.png'])
            ->assertMountedActionModalDontSee('notes.pdf')
            ->setActionData([
                'chosen' => ['media/old.jpg'],
                'files' => [UploadedFile::fake()->image('fresh.jpg', 1000, 500)],
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $gallery = $page->get('data.gallery');

        $this->assertCount(2, $gallery);
        $this->assertSame('media/old.jpg', $gallery[0]);
        $this->assertStringStartsWith('articles/gallery/', $gallery[1]);
        $disk->assertExists($gallery[1]);
        $disk->assertExists(Sizes::path($gallery[1], 'small'));
        $this->assertSame('گالری', collect(Library::rows())->firstWhere('path', $gallery[1])['place']);

        $page->callAction(TestAction::make('reorder')->schemaComponent('gallery')->arguments(['items' => [$gallery[1], $gallery[0], 'media/unknown.jpg']]))
            ->assertSet('data.gallery', [$gallery[1], $gallery[0]]);

        $page->callAction(TestAction::make('remove')->schemaComponent('gallery')->arguments(['path' => 'media/old.jpg']))
            ->assertSet('data.gallery', [$gallery[1]]);

        $disk->assertExists('media/old.jpg');

        $page->callAction(TestAction::make('pick')->schemaComponent('cover'), data: ['chosen' => ['media/other.png']])
            ->assertSet('data.cover', 'media/other.png');

        $page->callAction(TestAction::make('pick')->schemaComponent('cover'), data: ['chosen' => ['media/old.jpg']])
            ->assertSet('data.cover', 'media/old.jpg');

        $page->fillForm([
            'title' => 'نوشته گالری',
            'content' => '<p>متن</p>',
            'author_id' => $owner->getKey(),
            'published_at' => now()->toDateTimeString(),
        ])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::query()->where('title', 'نوشته گالری')->first();

        $this->assertNotNull($article);
        $this->assertSame('media/old.jpg', $article->cover);
        $this->assertSame([$gallery[1]], $article->gallery);
        $this->assertSame('تصویر شاخص نوشته گالری', collect(Library::rows())->firstWhere('path', 'media/old.jpg')['usage']);
    }

    /**
     * The field refuses a path that is not a picture in the library.
     *
     * A missing file and a path that climbs out of the disk (../.env) must both fail
     * validation, so a crafted request cannot point an article at arbitrary files.
     * Nothing is saved.
     */
    public function test_a_path_outside_the_library_is_refused(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/create')
            ->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateArticle::class)
            ->fillForm([
                'title' => 'نوشته',
                'content' => '<p>متن</p>',
                'cover' => 'articles/covers/missing.png',
                'gallery' => ['../.env'],
                'author_id' => $owner->getKey(),
                'published_at' => now()->toDateTimeString(),
            ])
            ->call('create')
            ->assertHasFormErrors(['cover', 'gallery']);

        $this->assertSame(0, Article::query()->count());
    }

    /**
     * Creates the owner account; as the first user it receives the owner role and every section.
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
     * Issues a one-hour panel token for the user, sent as the sanctum.panel_cookie cookie.
     */
    private function token(User $user): string
    {
        return app(AccessTokens::class)->issue($user, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);
    }
}
