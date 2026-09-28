<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Filament\Pages\Asset;
use App\Filament\Pages\Media;
use App\Models\Article;
use App\Models\Asset as AssetRecord;
use App\Models\Setting;
use App\Models\User;
use App\Support\Library;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the media library page (رسانه‌ها) and the file detail page.
 *
 * The library lists every file on the public disk, says where each one is used,
 * uploads new files, and is the only place that deletes a file. The detail page
 * edits a file's title, alt text, caption, and description, stored in the assets table.
 *
 * Extending:
 * - When a new model stores library paths, add it to Library and assert its usage label here.
 */
class MediaTest extends TestCase
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
     * The library lists every file and deleting one clears it wherever it is used.
     *
     * An avatar, an article cover, a gallery picture, and an unused file must all be
     * listed with their usage ("آواتار ...", "تصویر شاخص ...", "گالری ...", "بدون استفاده");
     * hidden files such as .gitignore are not. Deleting the avatar removes the file and
     * empties the user's avatar. Deleting the cover and gallery picture empties those
     * article fields. The unused file is left alone.
     */
    public function test_the_library_lists_every_public_file_and_delete_clears_its_use(): void
    {
        $disk = Storage::fake('public');
        $disk->put('avatars/face.png', 'face');
        $disk->put('articles/covers/cover.png', 'cover');
        $disk->put('articles/gallery/shot.png', 'shot');
        $disk->put('media/loose.png', 'loose');
        $disk->put('.gitignore', '*');

        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
            'firstname' => 'سارا',
            'lastname' => 'کریمی',
            'avatar' => 'avatars/face.png',
        ]);
        $article = Article::query()->create([
            'title' => 'نوشته تصویری',
            'content' => '<p>متن</p>',
            'cover' => 'articles/covers/cover.png',
            'gallery' => ['articles/gallery/shot.png'],
            'author_id' => $owner->getKey(),
            'published_at' => now(),
        ]);
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/media')
            ->assertOk()
            ->assertSee('رسانه‌ها')
            ->assertSee('face.png')
            ->assertSee('cover.png')
            ->assertSee('shot.png')
            ->assertSee('loose.png')
            ->assertSee('آواتار سارا کریمی')
            ->assertSee('تصویر شاخص نوشته تصویری')
            ->assertSee('گالری نوشته تصویری')
            ->assertSee('بدون استفاده')
            ->assertSee('جزئیات')
            ->assertDontSee('.gitignore');

        $face = collect(Library::rows())->firstWhere('path', 'avatars/face.png');
        $cover = collect(Library::rows())->firstWhere('path', 'articles/covers/cover.png');
        $shot = collect(Library::rows())->firstWhere('path', 'articles/gallery/shot.png');

        $this->assertIsArray($face);
        $this->assertIsArray($cover);
        $this->assertIsArray($shot);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(Media::class)
            ->assertActionExists('upload')
            ->callTableAction('delete', $face['__key']);

        $disk->assertMissing('avatars/face.png');
        $this->assertNull($owner->refresh()->avatar);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(Media::class)
            ->callTableAction('delete', $cover['__key'])
            ->callTableAction('delete', $shot['__key']);

        $article->refresh();
        $this->assertNull($article->cover);
        $this->assertNull($article->gallery);
        $disk->assertMissing('articles/covers/cover.png');
        $disk->assertMissing('articles/gallery/shot.png');
        $disk->assertExists('media/loose.png');
    }

    /**
     * The library's upload button stores the file under media/ and lists it with the place "رسانه".
     */
    public function test_a_file_can_be_uploaded_from_the_library(): void
    {
        $disk = Storage::fake('public');

        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/media')
            ->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(Media::class)
            ->callAction('upload', [
                'files' => [$this->picture('poster.png')],
            ])
            ->assertHasNoActionErrors();

        $stored = collect($disk->allFiles())->first(fn (string $path): bool => str_starts_with($path, 'media/') && str_ends_with($path, '.png'));

        $this->assertIsString($stored);
        $disk->assertExists($stored);
        $this->assertSame('رسانه', collect(Library::rows())->firstWhere('path', $stored)['place'] ?? null);
    }

    /**
     * Each file has a detail page with editable text and read-only image facts.
     *
     * The page shows the text fields and the image's name, pixel size, and file size.
     * An unknown key returns 404. Saving stores the texts in the assets table, and
     * deleting the file from the library removes both the file and its assets row.
     */
    public function test_a_file_has_a_detail_page_for_its_text_and_image_facts(): void
    {
        $disk = Storage::fake('public');
        $disk->put(
            'media/poster.png',
            (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );

        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);
        $row = collect(Library::rows())->firstWhere('path', 'media/poster.png');
        $this->assertIsArray($row);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/media/'.$row['__key'])
            ->assertOk()
            ->assertSee('عنوان')
            ->assertSee('متن جایگزین')
            ->assertSee('عنوان تصویر')
            ->assertSee('توضیحات')
            ->assertSee('اطلاعات تصویر')
            ->assertSee('poster.png')
            ->assertSee('1 × 1')
            ->assertSee('حجم');

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/media/missing')
            ->assertNotFound();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(Asset::class, ['asset' => $row['__key']])
            ->fillForm([
                'title' => 'نهال',
                'alt' => 'تصویر نهال',
                'caption' => 'نهال گردو',
                'description' => 'توضیح تصویر',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('assets', [
            'path' => 'media/poster.png',
            'title' => 'نهال',
            'alt' => 'تصویر نهال',
            'caption' => 'نهال گردو',
            'description' => 'توضیح تصویر',
        ]);

        Library::drop('media/poster.png');

        $this->assertNull(AssetRecord::query()->where('path', 'media/poster.png')->first());
        $disk->assertMissing('media/poster.png');
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
