<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Resources\Pages\Pages\DesignPage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Library;
use App\Support\Sizes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the page builder (صفحه‌ساز) of the pages section: the GrapesJS editor page and what it stores.
 *
 * The tests sign in with a real Sanctum panel cookie. They open the editor page, drive
 * save and store through Livewire the way resources/js/designer.js calls them, and check
 * that builder pictures take part in the media library like every other file.
 *
 * Extending:
 * - A new Livewire method the editor calls gets a test here.
 * - Tests that store files call Storage::fake('public') first so nothing reaches the real disk.
 */
class DesignerTest extends TestCase
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
     * The editor opens for an editor with the saved layout, and staff without the pages section get 403.
     *
     * The edit form links to it, and the list offers it as a row action.
     */
    public function test_the_builder_opens_only_for_page_editors(): void
    {
        $owner = $this->owner();
        $page = Page::query()->create([
            'title' => 'درباره ما',
            'author_id' => $owner->getKey(),
            'markup' => '<section class="hero"><h1>سلام دامون</h1></section>',
        ]);
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/pages/'.$page->getKey().'/design')
            ->assertOk()
            ->assertSee('صفحه‌ساز: درباره ما')
            ->assertSee('ذخیره')
            ->assertSee('مشخصات برگه')
            ->assertSee('damoon-designer', false)
            ->assertSee('designer.js', false)
            ->assertSee(Js::from($page->markup)->toHtml(), false);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditPage::class, ['record' => $page->getKey()])
            ->assertActionExists('design');

        $member = User::factory()->create([
            'username' => 'member_user',
            'email' => 'member@example.com',
            'phone' => '09120000002',
        ]);
        $member->grant([Section::ARTICLES]);

        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($member))
            ->get('/admin/pages/'.$page->getKey().'/design')
            ->assertForbidden();
    }

    /**
     * Saving stores the project without its asset list, the HTML, and the CSS; layout joins them for the site.
     *
     * A blank save clears the HTML and CSS, and HTML over the limit is refused.
     */
    public function test_saving_stores_the_design_html_and_css(): void
    {
        $owner = $this->owner();
        $page = Page::query()->create(['title' => 'خدمات', 'author_id' => $owner->getKey()]);
        $project = [
            'assets' => [['src' => '/storage/media/all.png']],
            'styles' => [['selectors' => ['hero'], 'style' => ['color' => 'red']]],
            'pages' => [['frames' => [['component' => ['type' => 'wrapper', 'components' => [['tagName' => 'section', 'classes' => ['hero']]]]]]]],
        ];
        $token = $this->open($owner, $page);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(DesignPage::class, ['record' => $page->getKey()])
            ->call('save', $project, ' <section class="hero">خدمات ما</section> ', '.hero{color:red;}')
            ->assertHasNoErrors()
            ->assertNotified('صفحه‌ساز ذخیره شد.');

        $page->refresh();
        $this->assertArrayNotHasKey('assets', $page->design);
        $this->assertSame('section', $page->design['pages'][0]['frames'][0]['component']['components'][0]['tagName']);
        $this->assertSame('<section class="hero">خدمات ما</section>', $page->markup);
        $this->assertSame('.hero{color:red;}', $page->style);
        $this->assertSame('<style>.hero{color:red;}</style>'."\n".'<section class="hero">خدمات ما</section>', $page->layout());

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(DesignPage::class, ['record' => $page->getKey()])
            ->call('save', ['pages' => []], '  ', '')
            ->call('save', ['pages' => []], str_repeat('a', DesignPage::LIMIT + 1), '')
            ->assertHasErrors('html');

        $page->refresh();
        $this->assertNull($page->markup);
        $this->assertNull($page->style);
        $this->assertSame('', $page->layout());
    }

    /**
     * An upload from the editor lands in Page::DESIGNS with its sizes and shows in the editor's picture list.
     *
     * A file that is not a picture is refused with a Persian message.
     */
    public function test_uploads_go_into_the_media_library(): void
    {
        $disk = Storage::fake('public');
        $owner = $this->owner();
        $page = Page::query()->create(['title' => 'گالری', 'author_id' => $owner->getKey()]);
        $token = $this->open($owner, $page);

        $component = Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(DesignPage::class, ['record' => $page->getKey()])
            ->set('upload', UploadedFile::fake()->image('photo.jpg', 800, 400))
            ->call('store')
            ->assertHasNoErrors();

        $files = $disk->files(Page::DESIGNS);
        $this->assertCount(1, $files);
        $disk->assertExists(Sizes::path($files[0], 'medium'));
        $this->assertContains('/storage/'.$files[0], array_column($component->instance()->assets(), 'src'));

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(DesignPage::class, ['record' => $page->getKey()])
            ->set('upload', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
            ->call('store')
            ->assertHasErrors(['upload' => 'image']);

        $this->assertCount(1, $disk->files(Page::DESIGNS));
    }

    /**
     * The media library names builder pictures with the page title, and deleting one takes it out of the design and HTML.
     */
    public function test_builder_pictures_show_their_use_and_leave_on_delete(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Page::DESIGNS, UploadedFile::fake()->image('hero.jpg', 800, 400), 'hero.jpg');
        $disk->putFileAs('media', UploadedFile::fake()->image('logo.jpg', 400, 400), 'logo.jpg');
        $owner = $this->owner();
        $hero = '/storage/'.Page::DESIGNS.'/hero.jpg';
        $logo = '/storage/media/logo.jpg';

        $page = Page::query()->create([
            'title' => 'خانه',
            'author_id' => $owner->getKey(),
            'design' => [
                'pages' => [['frames' => [['component' => ['type' => 'wrapper', 'components' => [
                    ['type' => 'image', 'attributes' => ['src' => $hero]],
                    ['type' => 'image', 'src' => $logo],
                ]]]]]],
            ],
            'markup' => '<img src="'.$hero.'" alt="بنر"><img src="'.$logo.'">',
        ]);

        $rows = collect(Library::rows());
        $this->assertSame('تصویر صفحه‌ساز', $rows->firstWhere('path', Page::DESIGNS.'/hero.jpg')['place']);
        $this->assertSame('صفحه‌ساز برگه خانه', $rows->firstWhere('path', Page::DESIGNS.'/hero.jpg')['usage']);
        $this->assertSame('صفحه‌ساز برگه خانه', $rows->firstWhere('path', 'media/logo.jpg')['usage']);

        Library::drop(Page::DESIGNS.'/hero.jpg');

        $page->refresh();
        $components = $page->design['pages'][0]['frames'][0]['component']['components'];
        $this->assertCount(1, $components);
        $this->assertSame($logo, $components[0]['src']);
        $this->assertSame('<img src="'.$logo.'">', $page->markup);
        $this->assertSame(['media/logo.jpg'], Page::sources($page->design, $page->markup));
        $disk->assertMissing(Page::DESIGNS.'/hero.jpg');
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
     * Opens the builder of a page with a fresh panel token and returns the token.
     *
     * Livewire tests run as the user the last panel request signed in.
     */
    private function open(User $user, Page $page): string
    {
        $token = $this->token($user);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/pages/'.$page->getKey().'/design')
            ->assertOk();

        return $token;
    }

    /**
     * A panel token for the given user.
     */
    private function token(User $user): string
    {
        return app(AccessTokens::class)->issue($user, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);
    }
}
