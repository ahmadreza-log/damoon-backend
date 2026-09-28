<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Filament\Pages\Asset;
use App\Filament\Pages\Media;
use App\Models\Article;
use App\Models\Setting;
use App\Models\User;
use App\Support\Library;
use App\Support\Sizes;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the WebP sizes built for every image (App\Support\Sizes).
 *
 * Each picture gets thumb (150×150 crop), small (480 wide), medium (960 wide), and
 * large (1600 wide) copies under sizes/<size>/ on the public disk. The tests check
 * the pixel sizes, that small pictures are never enlarged, that uploads, avatars, and
 * article pictures get sizes on save, that only the media library deletes them, and
 * the media:sizes command for older files.
 *
 * Extending:
 * - A new entry in Sizes::LIST needs an assertPixels line in the first test.
 */
class SizesTest extends TestCase
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
     * A 2000×1000 upload gets all four sizes, and deleting it from the library removes them.
     *
     * The crop is exactly 150×150; the other sizes keep the 2:1 ratio. The library uses
     * the small size as the preview, and the detail page lists every size with its pixels.
     */
    public function test_an_uploaded_image_gets_every_size_and_loses_them_on_delete(): void
    {
        $disk = Storage::fake('public');
        $owner = $this->owner();
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/media')
            ->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(Media::class)
            ->callAction('upload', [
                'files' => [UploadedFile::fake()->image('wide.jpg', 2000, 1000)],
            ])
            ->assertHasNoActionErrors();

        $stored = collect($disk->allFiles('media'))->first();
        $this->assertIsString($stored);

        $this->assertPixels($disk, Sizes::path($stored, 'thumb'), 150, 150);
        $this->assertPixels($disk, Sizes::path($stored, 'small'), 480, 240);
        $this->assertPixels($disk, Sizes::path($stored, 'medium'), 960, 480);
        $this->assertPixels($disk, Sizes::path($stored, 'large'), 1600, 800);

        $rows = collect(Library::rows());
        $this->assertCount(1, $rows);
        $this->assertSame(Sizes::path($stored, 'small'), $rows->first()['preview']);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/media/'.$rows->first()['__key'])
            ->assertOk()
            ->assertSee('اندازه‌ها')
            ->assertSee('بندانگشتی')
            ->assertSee('150 × 150')
            ->assertSee('1600 × 800')
            ->assertSee('ساخت اندازه‌ها');

        Library::drop($stored);

        foreach (array_keys(Sizes::LIST) as $size) {
            $disk->assertMissing(Sizes::path($stored, $size));
        }
    }

    /**
     * A picture that was put on disk without sizes can get them from its detail page.
     *
     * The page first says no sizes exist yet. The resize action builds them; a 300×200
     * picture keeps 300×200 for the wide sizes instead of being enlarged, while the
     * thumb is still cropped to 150×150.
     */
    public function test_a_small_image_is_not_enlarged_and_the_detail_page_rebuilds_sizes(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs('media', UploadedFile::fake()->image('tiny.png', 300, 200), 'tiny.png');
        $owner = $this->owner();
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);
        $key = hash('sha1', 'media/tiny.png');

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/media/'.$key)
            ->assertOk()
            ->assertSee('هنوز اندازه‌ای ساخته نشده است.');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(Asset::class, ['asset' => $key])
            ->callAction('resize')
            ->assertSee('300 × 200');

        $this->assertPixels($disk, Sizes::path('media/tiny.png', 'large'), 300, 200);
        $this->assertPixels($disk, Sizes::path('media/tiny.png', 'thumb'), 150, 150);
    }

    /**
     * Saving a user or an article builds sizes for the pictures it points at.
     *
     * The avatar gets sizes and the panel shows its thumb. The article cover gets
     * sizes on create, and a gallery picture gets them when it is added later.
     * Deleting the article keeps every size; only Library::drop removes them.
     */
    public function test_article_images_and_avatars_get_sizes(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs('avatars', UploadedFile::fake()->image('face.jpg', 400, 400), 'face.jpg');
        $disk->putFileAs('articles/covers', UploadedFile::fake()->image('cover.jpg', 1200, 600), 'cover.jpg');
        $disk->putFileAs('articles/gallery', UploadedFile::fake()->image('shot.png', 800, 800), 'shot.png');

        $owner = $this->owner(['avatar' => 'avatars/face.jpg']);

        $disk->assertExists(Sizes::path('avatars/face.jpg', 'thumb'));
        $this->assertStringEndsWith('sizes/thumb/avatars/face.jpg.webp', (string) $owner->getFilamentAvatarUrl());

        $article = Article::query()->create([
            'title' => 'نوشته با تصویر',
            'content' => '<p>متن</p>',
            'cover' => 'articles/covers/cover.jpg',
            'author_id' => $owner->getKey(),
            'published_at' => now(),
        ]);

        $this->assertPixels($disk, Sizes::path('articles/covers/cover.jpg', 'medium'), 960, 480);
        $disk->assertMissing(Sizes::path('articles/gallery/shot.png', 'thumb'));

        $article->update(['gallery' => ['articles/gallery/shot.png']]);

        $disk->assertExists(Sizes::path('articles/gallery/shot.png', 'thumb'));

        $article->delete();

        $disk->assertExists(Sizes::path('articles/covers/cover.jpg', 'medium'));
        $disk->assertExists(Sizes::path('articles/gallery/shot.png', 'thumb'));

        Library::drop('articles/covers/cover.jpg');

        $disk->assertMissing(Sizes::path('articles/covers/cover.jpg', 'medium'));
    }

    /**
     * php artisan media:sizes builds sizes for images already on disk and skips other files.
     */
    public function test_the_command_builds_sizes_for_older_images(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs('media', UploadedFile::fake()->image('old.jpg', 1000, 500), 'old.jpg');
        $disk->put('media/notes.pdf', 'pdf');

        $this->artisan('media:sizes')
            ->expectsOutput('Built sizes for 1 images.')
            ->assertSuccessful();

        $this->assertPixels($disk, Sizes::path('media/old.jpg', 'small'), 480, 240);
        $disk->assertMissing(Sizes::path('media/notes.pdf', 'small'));
    }

    /**
     * Creates the owner account, with any extra columns such as an avatar path.
     *
     * @param  array<string, mixed>  $extra
     */
    private function owner(array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ], $extra));
    }

    /**
     * Asserts that the file exists, is a WebP image, and has exactly the given width and height.
     */
    private function assertPixels(FilesystemAdapter $disk, string $path, int $width, int $height): void
    {
        $disk->assertExists($path);
        $info = getimagesizefromstring((string) $disk->get($path));

        $this->assertIsArray($info);
        $this->assertSame('image/webp', $info['mime']);
        $this->assertSame([$width, $height], [$info[0], $info[1]]);
    }
}
