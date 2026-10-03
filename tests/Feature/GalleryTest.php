<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Fields\MediaGrid;
use App\Filament\Fields\MediaPicker;
use App\Filament\Resources\Galleries\Pages\CreateGallery;
use App\Filament\Resources\Galleries\Pages\EditGallery;
use App\Models\Asset;
use App\Models\Gallery;
use App\Models\Kind;
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
 * Covers the galleries section in the panel, the Gallery model, the kind-aware MediaPicker, and the gallery routes of the content API.
 *
 * Extending:
 * - A new gallery field needs an assertFormFieldExists, a value in the create test, and a key in the API test.
 * - Tests that store files call Storage::fake('public') first so nothing reaches the real disk.
 */
class GalleryTest extends TestCase
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
     * A video gallery offers and accepts only videos, stores uploads in its folder, and keeps its kind on edit.
     *
     * The library tab of a video gallery lists the video and hides the picture and the audio file.
     * The upload tab names the video formats. A picture path is refused on save. Switching the
     * kind on the create form empties the chosen files. Members need the galleries section.
     */
    public function test_a_video_gallery_takes_only_videos_from_the_library_and_uploads(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs('media', UploadedFile::fake()->image('photo.jpg', 600, 400), 'photo.jpg');
        $disk->put('media/clip.mp4', 'video');
        $disk->put('media/song.mp3', 'ID3');
        $owner = $this->owner();
        $token = $this->token($owner);

        $this->assertSame(['media/clip.mp4'], array_column(MediaGrid::make('chosen')->kind(Kind::Video)->tiles(), 'path'));
        $this->assertSame(['media/song.mp3'], array_column(MediaGrid::make('chosen')->kind(Kind::Audio)->tiles(), 'path'));
        $this->assertSame(['media/photo.jpg'], array_column(MediaGrid::make('chosen')->tiles(), 'path'));

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/galleries/create')
            ->assertOk()
            ->assertSee('عنوان')
            ->assertSee('نوع گالری')
            ->assertSee('گالری تصاویر')
            ->assertSee('گالری ویدئو')
            ->assertSee('گالری صدا')
            ->assertSee('توضیحات');

        $page = Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateGallery::class)
            ->assertFormFieldExists('title')
            ->assertFormFieldExists('slug')
            ->assertFormFieldExists('kind')
            ->assertFormFieldExists('description')
            ->assertFormFieldExists('items', fn (MediaPicker $field): bool => $field->isMultiple() && $field->getKind() === Kind::Image)
            ->fillForm(['kind' => Kind::Video->value])
            ->assertFormFieldExists('items', fn (MediaPicker $field): bool => $field->getKind() === Kind::Video && $field->getDirectory() === 'galleries/video')
            ->mountAction(TestAction::make('pick')->schemaComponent('items'))
            ->assertMountedActionModalSee(['clip.mp4', 'MP4', 'WEBM'])
            ->assertMountedActionModalDontSee(['photo.jpg', 'song.mp3'])
            ->setActionData([
                'chosen' => ['media/clip.mp4', 'media/photo.jpg'],
                'files' => [UploadedFile::fake()->create('fresh.mp4', 300, 'video/mp4')],
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $items = $page->get('data.items');

        $this->assertCount(2, $items);
        $this->assertSame('media/clip.mp4', $items[0]);
        $this->assertStringStartsWith('galleries/video/', $items[1]);
        $disk->assertExists($items[1]);
        $this->assertSame('گالری ویدئو', collect(Library::rows())->firstWhere('path', $items[1])['place']);

        $page->fillForm(['kind' => Kind::Audio->value])
            ->assertSet('data.items', [])
            ->fillForm(['kind' => Kind::Video->value, 'items' => ['media/photo.jpg']])
            ->call('create')
            ->assertHasFormErrors(['items']);

        $page->fillForm([
            'title' => 'همایش سالانه',
            'description' => 'ویدئوهای همایش',
            'items' => $items,
        ])
            ->call('create')
            ->assertHasNoFormErrors();

        $gallery = Gallery::query()->where('title', 'همایش سالانه')->firstOrFail();

        $this->assertSame('همایش-سالانه', $gallery->slug);
        $this->assertSame(Kind::Video, $gallery->kind);
        $this->assertSame($items, $gallery->items);
        $this->assertSame('گالری ویدئو همایش سالانه', collect(Library::rows())->firstWhere('path', 'media/clip.mp4')['usage']);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditGallery::class, ['record' => $gallery->getKey()])
            ->assertFormFieldDisabled('kind')
            ->fillForm(['title' => 'همایش سالانه ۱۴۰۵', 'kind' => Kind::Image->value, 'items' => ['media/clip.mp4']])
            ->call('save')
            ->assertHasNoFormErrors();

        $gallery->refresh();
        $this->assertSame('همایش سالانه ۱۴۰۵', $gallery->title);
        $this->assertSame(Kind::Video, $gallery->kind);
        $this->assertSame(['media/clip.mp4'], $gallery->items);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/galleries')
            ->assertOk()
            ->assertSee('همایش سالانه ۱۴۰۵')
            ->assertSee('گالری ویدئو');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditGallery::class, ['record' => $gallery->getKey()])
            ->callAction('delete');

        $this->assertNull(Gallery::query()->find($gallery->getKey()));
        $disk->assertExists('media/clip.mp4');

        $editor = User::factory()->create(['username' => 'editor_user', 'email' => 'editor@example.com', 'phone' => '09120000003']);
        $editor->grant([Section::GALLERIES]);
        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($editor))->get('/admin/galleries')->assertOk();

        $member = User::factory()->create(['username' => 'member_user', 'email' => 'member@example.com', 'phone' => '09120000002']);
        $member->grant([Section::MEDIA]);
        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($member))->get('/admin/galleries')->assertForbidden();
    }

    /**
     * A picture gallery's uploads get their sizes, and the picture picker keeps refusing other kinds.
     */
    public function test_a_picture_gallery_builds_sizes_and_refuses_audio(): void
    {
        $disk = Storage::fake('public');
        $disk->put('media/song.mp3', 'ID3');
        $owner = $this->owner();
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)->get('/admin/galleries/create')->assertOk();

        $page = Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateGallery::class)
            ->callAction(TestAction::make('pick')->schemaComponent('items'), data: [
                'files' => [UploadedFile::fake()->image('stage.jpg', 1200, 800)],
            ])
            ->assertHasNoFormErrors();

        $items = $page->get('data.items');

        $this->assertStringStartsWith('galleries/image/', $items[0]);
        $disk->assertExists(Sizes::path($items[0], 'small'));

        $page->fillForm(['title' => 'افتتاحیه', 'items' => [...$items, 'media/song.mp3']])
            ->call('create')
            ->assertHasFormErrors(['items']);

        $page->fillForm(['items' => $items])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(Kind::Image, Gallery::query()->where('title', 'افتتاحیه')->firstOrFail()->kind);
    }

    /**
     * The API lists galleries newest first, filters them by kind and text, and sends one gallery with its files.
     *
     * Each file carries its address, type, size, and the texts from the media page; pictures also
     * carry their sizes. A file missing from the disk is left out. An unknown kind is refused.
     */
    public function test_gallery_api_lists_and_shows_galleries(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs('galleries/image', UploadedFile::fake()->image('one.jpg', 800, 600), 'one.jpg');
        $disk->putFileAs('galleries/image', UploadedFile::fake()->image('two.jpg', 800, 600), 'two.jpg');
        $disk->put('galleries/audio/talk.mp3', 'ID3');
        Asset::query()->create(['path' => 'galleries/image/one.jpg', 'title' => 'سالن اصلی', 'alt' => 'نمای سالن', 'caption' => 'روز اول']);

        $pictures = Gallery::factory()->create([
            'title' => 'نمایشگاه الکامپ',
            'kind' => Kind::Image,
            'description' => 'غرفهٔ دامون',
            'items' => ['galleries/image/one.jpg', 'galleries/image/two.jpg', 'galleries/image/gone.jpg'],
            'created_at' => now()->subDay(),
        ]);
        Gallery::factory()->create([
            'title' => 'گفتگو با مشتریان',
            'kind' => Kind::Audio,
            'description' => 'پادکست',
            'items' => ['galleries/audio/talk.mp3'],
        ]);

        $this->get('/v1/galleries')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'گفتگو با مشتریان')
            ->assertJsonPath('data.0.kind', 'audio')
            ->assertJsonPath('data.0.cover', null)
            ->assertJsonPath('data.1.kind', 'image')
            ->assertJsonPath('data.1.count', 3)
            ->assertJsonPath('data.1.cover.path', 'galleries/image/one.jpg')
            ->assertJsonMissingPath('data.0.items');
        $this->get('/v1/galleries?kind=image')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'نمایشگاه الکامپ');
        $this->get('/v1/galleries?q=پادکست')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'audio');
        $this->get('/v1/galleries?kind=pdf')->assertStatus(422)->assertJsonValidationErrors('kind');
        $this->get('/v1/galleries?per_page=51')->assertStatus(422)->assertJsonValidationErrors('per_page');

        $response = $this->get('/v1/galleries/'.urlencode((string) $pictures->slug))
            ->assertOk()
            ->assertJsonPath('data.description', 'غرفهٔ دامون')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.path', 'galleries/image/one.jpg')
            ->assertJsonPath('data.items.0.title', 'سالن اصلی')
            ->assertJsonPath('data.items.0.alt', 'نمای سالن')
            ->assertJsonPath('data.items.0.caption', 'روز اول')
            ->assertJsonPath('data.items.0.mime', 'image/jpeg')
            ->assertJsonPath('data.items.1.name', 'two.jpg');

        $this->assertSame(url('/galleries/'.$pictures->slug), $response->json('data.url'));
        $this->assertSame(url('/storage/galleries/image/one.jpg'), $response->json('data.items.0.url'));
        $this->assertStringContainsString('/storage/', (string) $response->json('data.items.0.sizes.thumb'));

        $audio = Gallery::query()->where('kind', Kind::Audio->value)->firstOrFail();
        $this->get('/v1/galleries/'.urlencode((string) $audio->slug))
            ->assertOk()
            ->assertJsonPath('data.items.0.url', url('/storage/galleries/audio/talk.mp3'))
            ->assertJsonPath('data.items.0.sizes', null);

        $this->get('/v1/galleries/missing')->assertNotFound()->assertJsonPath('message', 'گالری پیدا نشد.');
        $this->get('/v1/media?type=audio')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.kind', 'audio');
    }

    /**
     * Deleting a file from the media library takes it out of every gallery that holds it.
     */
    public function test_deleting_a_library_file_clears_it_from_galleries(): void
    {
        $disk = Storage::fake('public');
        $disk->put('galleries/video/a.mp4', 'video');
        $disk->put('galleries/video/b.mp4', 'video');
        $gallery = Gallery::factory()->create(['kind' => Kind::Video, 'items' => ['galleries/video/a.mp4', 'galleries/video/b.mp4']]);

        Library::drop('galleries/video/a.mp4');
        $this->assertSame(['galleries/video/b.mp4'], $gallery->refresh()->items);

        Library::drop('galleries/video/b.mp4');
        $this->assertNull($gallery->refresh()->items);
        $disk->assertMissing('galleries/video/b.mp4');
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
}
