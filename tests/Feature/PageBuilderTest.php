<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Filament\Builder\Block;
use App\Filament\Builder\Callout;
use App\Filament\Builder\Code;
use App\Filament\Builder\Faq;
use App\Filament\Builder\Gallery;
use App\Filament\Builder\Hero;
use App\Filament\Builder\Text;
use App\Filament\Builder\Video;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Library;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Redberry\PageBuilderPlugin\Models\PageBuilderBlock;
use Tests\TestCase;

/**
 * Covers the page builder (صفحه‌ساز) on the pages form and the blocks behind it.
 *
 * Blocks are added and edited through the package's actions on the page form, saved
 * into page_builder_blocks after the page, drawn by Page::layout, cleared when the
 * page is deleted, and listed in the media library by the pictures they use.
 *
 * Extending:
 * - A new block needs its view drawn once here, through layout or the view itself.
 * - Tests that store files call Storage::fake('public') first so nothing reaches the real disk.
 */
class PageBuilderTest extends TestCase
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
     * Blocks added on the create form are saved with the page, in order, and drawn for the site.
     *
     * The form shows the Persian page builder labels. A hero with a library picture, a call
     * to action, and a question list are added through the create action, the page is saved,
     * and layout must draw all three in the same order with the picture's large size.
     */
    public function test_blocks_added_on_the_form_are_saved_and_drawn(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Block::FOLDER, $this->picture('hero.png'), 'hero.png');
        $owner = $this->owner();
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/pages/create')
            ->assertOk()
            ->assertSee('صفحه‌ساز')
            ->assertSee('افزودن بلوک')
            ->assertSee('پیش‌نمایش صفحه‌ساز');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreatePage::class)
            ->assertActionExists(TestAction::make('select-block')->schemaComponent('builder'))
            ->fillForm([
                'title' => 'خانه',
                'author_id' => $owner->getKey(),
                'published_at' => now()->toDateTimeString(),
            ])
            ->callAction(TestAction::make('create')->schemaComponent('builder')->arguments(['block_type' => Hero::class]), [
                'data' => [
                    'heading' => 'به دامون خوش آمدید',
                    'text' => 'سامانه مدیریت محتوای فارسی',
                    'image' => Block::FOLDER.'/hero.png',
                    'button' => 'شروع کنید',
                    'link' => '/start',
                ],
            ])
            ->assertHasNoFormErrors()
            ->callAction(TestAction::make('create')->schemaComponent('builder')->arguments(['block_type' => Callout::class]), [
                'data' => [
                    'heading' => 'همین حالا تماس بگیرید',
                    'button' => 'تماس',
                    'link' => '/contact',
                ],
            ])
            ->assertHasNoFormErrors()
            ->callAction(TestAction::make('create')->schemaComponent('builder')->arguments(['block_type' => Faq::class]), [
                'data' => [
                    'heading' => 'سوالات',
                    'items' => [
                        ['question' => 'دامون رایگان است؟', 'answer' => 'بله.'],
                    ],
                ],
            ])
            ->assertHasNoFormErrors()
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->where('title', 'خانه')->firstOrFail();
        $blocks = $page->pageBuilderBlocks;

        $this->assertSame([Hero::class, Callout::class, Faq::class], $blocks->pluck('block_type')->all());
        $this->assertSame('به دامون خوش آمدید', $blocks[0]->data['heading']);
        $this->assertSame(Block::FOLDER.'/hero.png', $blocks[0]->data['image']);

        $html = $page->layout();

        $this->assertStringContainsString('به دامون خوش آمدید', $html);
        $this->assertStringContainsString('/storage/'.Block::FOLDER.'/hero.png', $html);
        $this->assertStringContainsString('href="/start"', $html);
        $this->assertStringContainsString('<summary', $html);
        $this->assertStringContainsString('دامون رایگان است؟', $html);
        $this->assertLessThan(strpos($html, 'همین حالا تماس بگیرید'), strpos($html, 'به دامون خوش آمدید'));
        $this->assertLessThan(strpos($html, 'دامون رایگان است؟'), strpos($html, 'همین حالا تماس بگیرید'));
    }

    /**
     * Saved blocks can be edited, reordered, and removed from the edit form.
     *
     * A button link that is not a site path or a web address, such as javascript:, is refused.
     */
    public function test_saved_blocks_can_be_edited_reordered_and_removed(): void
    {
        $owner = $this->owner();
        $page = Page::query()->create(['title' => 'درباره ما', 'author_id' => $owner->getKey()]);
        $first = $page->pageBuilderBlocks()->create(['block_type' => Text::class, 'order' => 1, 'data' => ['heading' => 'داستان ما', 'text' => '<p>از ۱۴۰۰</p>']]);
        $second = $page->pageBuilderBlocks()->create(['block_type' => Callout::class, 'order' => 2, 'data' => ['heading' => 'همکاری', 'button' => 'تماس', 'link' => '/contact']]);
        $third = $page->pageBuilderBlocks()->create(['block_type' => Video::class, 'order' => 3, 'data' => ['url' => 'https://www.aparat.com/v/abc123']]);
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/pages/'.$page->getKey().'/edit')
            ->assertOk()
            ->assertSee('داستان ما');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditPage::class, ['record' => $page->getKey()])
            ->callAction(TestAction::make('edit')->schemaComponent('builder')->arguments(['item' => $first->id, 'index' => 0]), [
                'data' => ['heading' => 'داستان دامون', 'text' => '<p>از ۱۴۰۰ تا امروز</p>'],
            ])
            ->assertHasNoFormErrors()
            ->callAction(TestAction::make('reorder')->schemaComponent('builder')->arguments(['items' => [$second->id, $first->id, $third->id]]))
            ->callAction(TestAction::make('delete')->schemaComponent('builder')->arguments(['item' => $third->id, 'index' => 2]))
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction(TestAction::make('create')->schemaComponent('builder')->arguments(['block_type' => Callout::class]), [
                'data' => ['heading' => 'خطرناک', 'button' => 'کلیک', 'link' => 'javascript:alert(1)'],
            ])
            ->assertHasActionErrors(['data.link' => 'regex']);

        $blocks = $page->refresh()->pageBuilderBlocks;

        $this->assertSame([$second->id, $first->id], $blocks->pluck('id')->all());
        $this->assertSame('داستان دامون', $blocks[1]->data['heading']);
        $this->assertStringContainsString('از ۱۴۰۰ تا امروز', $blocks[1]->data['text']);
        $this->assertNull(PageBuilderBlock::query()->find($third->id));
    }

    /**
     * Deleting a page deletes its blocks, since the polymorphic owner has no foreign key.
     *
     * layout skips a block type that is no longer offered, and the code block reaches the
     * site as written while the panel preview only shows it as text.
     */
    public function test_layout_skips_unknown_blocks_and_page_delete_clears_blocks(): void
    {
        $owner = $this->owner();
        $page = Page::query()->create(['title' => 'آزمایش', 'author_id' => $owner->getKey()]);
        $code = $page->pageBuilderBlocks()->create(['block_type' => Code::class, 'order' => 1, 'data' => ['language' => 'html', 'code' => '<div class="promo">ویژه</div>']]);
        $page->pageBuilderBlocks()->create(['block_type' => 'App\\Filament\\Builder\\Removed', 'order' => 2, 'data' => ['heading' => 'حذف‌شده']]);

        $html = $page->layout();

        $this->assertStringContainsString('<div class="promo">ویژه</div>', $html);
        $this->assertStringNotContainsString('حذف‌شده', $html);

        $preview = view(Code::getView(), ['block' => ['data' => $code->data]])->render();

        $this->assertStringContainsString('&lt;div class=&quot;promo&quot;&gt;', $preview);
        $this->assertStringNotContainsString('<div class="promo">', $preview);

        $page->delete();

        $this->assertSame(0, PageBuilderBlock::query()->count());
    }

    /**
     * Block pictures show their use in the media library and are taken out when the file is deleted.
     *
     * A gallery keeps its other pictures, and a hero loses its only picture.
     */
    public function test_block_pictures_show_in_the_library_and_are_cleared_on_delete(): void
    {
        $disk = Storage::fake('public');

        foreach (['hero.png', 'one.png', 'two.png'] as $name) {
            $disk->putFileAs(Block::FOLDER, $this->picture($name), $name);
        }

        $owner = $this->owner();
        $page = Page::query()->create(['title' => 'نمونه کارها', 'author_id' => $owner->getKey()]);
        $hero = $page->pageBuilderBlocks()->create(['block_type' => Hero::class, 'order' => 1, 'data' => ['heading' => 'نمونه‌ها', 'image' => Block::FOLDER.'/hero.png']]);
        $gallery = $page->pageBuilderBlocks()->create(['block_type' => Gallery::class, 'order' => 2, 'data' => ['images' => [Block::FOLDER.'/one.png', Block::FOLDER.'/two.png'], 'columns' => 2]]);

        $rows = collect(Library::rows());

        $this->assertSame('تصویر صفحه‌ساز', $rows->firstWhere('path', Block::FOLDER.'/hero.png')['place']);
        $this->assertSame('بلوک بنر اصلی برگه نمونه کارها', $rows->firstWhere('path', Block::FOLDER.'/hero.png')['usage']);
        $this->assertSame('بلوک گالری برگه نمونه کارها', $rows->firstWhere('path', Block::FOLDER.'/two.png')['usage']);

        Library::drop(Block::FOLDER.'/one.png');
        Library::drop(Block::FOLDER.'/hero.png');

        $this->assertSame([Block::FOLDER.'/two.png'], $gallery->refresh()->data['images']);
        $this->assertNull($hero->refresh()->data['image']);
        $this->assertSame('نمونه‌ها', $hero->data['heading']);
        $disk->assertMissing(Block::FOLDER.'/one.png');
    }

    /**
     * The video block turns Aparat and YouTube page addresses into player addresses.
     */
    public function test_video_addresses_become_players(): void
    {
        $this->assertSame('https://www.aparat.com/video/video/embed/videohash/abc123/vt/frame', Video::embed('https://www.aparat.com/v/abc123'));
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', Video::embed('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', Video::embed('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertNull(Video::embed('https://example.com/video'));
        $this->assertNull(Video::embed(null));
    }

    /**
     * An added block is labelled with its name and heading, or with its place when the heading is empty.
     */
    public function test_block_labels_use_the_heading(): void
    {
        $this->assertSame('بنر اصلی — خوش آمدید', Hero::getBlockLabel(['data' => ['heading' => 'خوش آمدید']], 0));
        $this->assertSame('گالری 2', Gallery::getBlockLabel(['data' => ['heading' => '']], 1));
        $this->assertSame('ویدیو', Video::getBlockLabel());
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
