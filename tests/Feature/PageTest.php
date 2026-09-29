<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Library;
use App\Support\Sizes;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the pages section (برگه‌ها) in the panel and the Page model behind it.
 *
 * The tests sign in with a real Sanctum panel cookie and drive the Filament pages
 * through Livewire: the create form and every field on it, editing, deleting, slug
 * building, the parent list, body pictures in the media library, and the pages permission.
 *
 * Extending:
 * - A new page field needs an assertFormFieldExists and a value in the create test.
 * - Tests that store files call Storage::fake('public') first so nothing reaches the real disk.
 */
class PageTest extends TestCase
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
     * Walks one page through its whole life in the panel.
     *
     * The menu shows برگه‌ها, and the create page shows every Persian label. The form is
     * filled with a JSON body holding a paragraph and a code block, a cover picked from
     * the library, a parent page, an order, and SEO text. The saved record must hold each
     * value and render the body back to HTML. The title is then edited, the list shows
     * the new title, and deleting the page keeps the cover file, because files belong to
     * the media library. Deleting the parent moves its child page to the top level.
     */
    public function test_a_page_can_be_created_edited_and_deleted(): void
    {
        $disk = Storage::fake('public');
        $owner = $this->owner();
        $disk->putFileAs(Page::COVERS, $this->picture('cover.png'), 'cover.png');
        $parent = Page::query()->create([
            'title' => 'درباره ما',
            'content' => '<p>درباره</p>',
            'author_id' => $owner->getKey(),
            'published_at' => now(),
        ]);
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/pages/create')
            ->assertOk()
            ->assertSee('برگه‌ها')
            ->assertSee('عنوان')
            ->assertSee('نامک')
            ->assertSee('محتوا')
            ->assertSee('تصویر شاخص')
            ->assertSee('برگهٔ مادر')
            ->assertSee('ترتیب')
            ->assertSee('نویسنده')
            ->assertSee('تاریخ انتشار')
            ->assertSee('اجازه به ارسال دیدگاه')
            ->assertSee('سئو');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreatePage::class)
            ->assertFormFieldExists('title')
            ->assertFormFieldExists('slug')
            ->assertFormFieldExists('content')
            ->assertFormFieldExists('cover')
            ->assertFormFieldExists('parent_id')
            ->assertFormFieldExists('position')
            ->assertFormFieldExists('author_id')
            ->assertFormFieldExists('published_at')
            ->assertFormSet(['commentable' => true])
            ->assertFormFieldExists('seo_meta.title')
            ->assertFormFieldExists('seo_meta.description')
            ->assertFormFieldExists('seo_meta.og_image')
            ->fillForm([
                'title' => 'تیم ما',
                'slug' => 'تیم',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'اعضای تیم']]],
                        ['type' => 'customBlock', 'attrs' => ['id' => 'code', 'config' => ['language' => 'html', 'code' => '<div class="team">تیم</div>']]],
                    ],
                ],
                'cover' => Page::COVERS.'/cover.png',
                'parent_id' => $parent->getKey(),
                'position' => 3,
                'author_id' => $owner->getKey(),
                'published_at' => now()->toDateTimeString(),
                'seo_meta' => [
                    'title' => 'عنوان سئو برگه',
                    'description' => 'توضیح سئو برگه',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->where('slug', 'تیم')->first();

        $this->assertNotNull($page);
        $this->assertSame('تیم ما', $page->title);
        $this->assertSame('doc', $page->content['type']);
        $this->assertIsArray(json_decode((string) DB::table('pages')->where('id', $page->getKey())->value('content'), true));
        $this->assertStringContainsString('<p>اعضای تیم</p>', $page->html());
        $this->assertStringContainsString('<div class="team">تیم</div>', $page->html());
        $this->assertSame(Page::COVERS.'/cover.png', $page->cover);
        $this->assertSame($parent->getKey(), $page->parent_id);
        $this->assertSame(3, $page->position);
        $this->assertSame($owner->getKey(), $page->author_id);
        $this->assertTrue($page->commentable);
        $this->assertSame('عنوان سئو برگه', $page->seoMeta->title);
        $this->assertSame('توضیح سئو برگه', $page->seoMeta->description);
        $this->assertSame('درباره ما › تیم ما', $page->trail());

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditPage::class, ['record' => $page->getKey()])
            ->fillForm(['title' => 'تیم دامون', 'commentable' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('تیم دامون', $page->refresh()->title);
        $this->assertFalse($page->commentable);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/pages')
            ->assertOk()
            ->assertSee('تیم دامون')
            ->assertSee('درباره ما');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ListPages::class)
            ->assertActionExists('create')
            ->assertTableActionExists('edit')
            ->callTableAction('delete', $parent);

        $this->assertNull(Page::query()->find($parent->getKey()));
        $this->assertNull($page->refresh()->parent_id);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditPage::class, ['record' => $page->getKey()])
            ->callAction('delete');

        $this->assertNull(Page::query()->find($page->getKey()));
        $disk->assertExists(Page::COVERS.'/cover.png');
    }

    /**
     * A page saved without a slug gets one from its title, with -2 when it is taken.
     */
    public function test_a_blank_slug_is_built_from_the_title(): void
    {
        $owner = $this->owner();

        $first = Page::query()->create(['title' => 'تماس با ما', 'content' => '<p>اول</p>', 'author_id' => $owner->getKey()]);
        $second = Page::query()->create(['title' => 'تماس با ما', 'content' => '<p>دوم</p>', 'author_id' => $owner->getKey()]);

        $this->assertSame('تماس-با-ما', $first->slug);
        $this->assertSame('تماس-با-ما-2', $second->slug);
    }

    /**
     * The parent list shows full trails and hides the page itself and every page under it.
     *
     * The body editor is the same as the article editor, with pictures in Page::FOLDER.
     */
    public function test_the_parent_list_hides_the_page_and_its_children(): void
    {
        $owner = $this->owner();
        $root = Page::query()->create(['title' => 'خدمات', 'content' => '<p>ریشه</p>', 'author_id' => $owner->getKey()]);
        $child = Page::query()->create(['title' => 'طراحی', 'content' => '<p>فرزند</p>', 'parent_id' => $root->getKey(), 'author_id' => $owner->getKey()]);
        $grandchild = Page::query()->create(['title' => 'لوگو', 'content' => '<p>نوه</p>', 'parent_id' => $child->getKey(), 'author_id' => $owner->getKey()]);
        $other = Page::query()->create(['title' => 'قوانین', 'content' => '<p>دیگر</p>', 'author_id' => $owner->getKey()]);
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/pages/'.$child->getKey().'/edit')
            ->assertOk()
            ->assertSee('خدمات');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditPage::class, ['record' => $child->getKey()])
            ->assertFormFieldExists('parent_id', function (Select $field) use ($root, $child, $grandchild, $other): bool {
                $options = $field->getOptions();

                return ($options[$root->getKey()] ?? null) === 'خدمات'
                    && ($options[$other->getKey()] ?? null) === 'قوانین'
                    && ! array_key_exists($child->getKey(), $options)
                    && ! array_key_exists($grandchild->getKey(), $options);
            })
            ->assertFormFieldExists('content', fn (RichEditor $field): bool => $field->isJson()
                && $field->hasToolbarButton('customBlocks')
                && $field->getFileAttachmentsDirectory() === Page::FOLDER);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreatePage::class)
            ->assertFormFieldExists('parent_id', fn (Select $field): bool => ($field->getOptions()[$grandchild->getKey()] ?? null) === 'خدمات › طراحی › لوگو');
    }

    /**
     * Pictures inside a page body behave like every other library file.
     *
     * Saving builds their sizes. The media library names the cover and body pictures
     * with this page's title. Deleting a body picture from the library takes it out of
     * the body, and deleting the cover from the library clears it from the page.
     */
    public function test_page_pictures_get_sizes_and_show_their_use_in_the_library(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Page::FOLDER, UploadedFile::fake()->image('inside.jpg', 1200, 600), 'inside.jpg');
        $disk->putFileAs(Page::FOLDER, UploadedFile::fake()->image('second.jpg', 800, 400), 'second.jpg');
        $disk->putFileAs(Page::COVERS, UploadedFile::fake()->image('cover.jpg', 800, 400), 'cover.jpg');
        $owner = $this->owner();
        $picture = fn (string $path): array => ['type' => 'image', 'attrs' => ['id' => $path, 'src' => '/storage/'.$path, 'alt' => 'تصویر']];

        $page = Page::query()->create([
            'title' => 'برگه تصویری',
            'content' => [
                'type' => 'doc',
                'content' => [
                    $picture(Page::FOLDER.'/inside.jpg'),
                    $picture(Page::FOLDER.'/second.jpg'),
                ],
            ],
            'cover' => Page::COVERS.'/cover.jpg',
            'author_id' => $owner->getKey(),
        ]);

        $disk->assertExists(Sizes::path(Page::FOLDER.'/inside.jpg', 'medium'));
        $disk->assertExists(Sizes::path(Page::COVERS.'/cover.jpg', 'medium'));

        $rows = collect(Library::rows());
        $this->assertSame('تصویر محتوای برگه', $rows->firstWhere('path', Page::FOLDER.'/second.jpg')['place']);
        $this->assertSame('محتوای برگه برگه تصویری', $rows->firstWhere('path', Page::FOLDER.'/second.jpg')['usage']);
        $this->assertSame('تصویر شاخص برگه', $rows->firstWhere('path', Page::COVERS.'/cover.jpg')['place']);
        $this->assertSame('تصویر شاخص برگه برگه تصویری', $rows->firstWhere('path', Page::COVERS.'/cover.jpg')['usage']);

        Library::drop(Page::FOLDER.'/second.jpg');
        Library::drop(Page::COVERS.'/cover.jpg');

        $page->refresh();
        $this->assertSame([Page::FOLDER.'/inside.jpg'], Page::images($page->content));
        $this->assertNull($page->cover);
        $disk->assertMissing(Page::FOLDER.'/second.jpg');
    }

    /**
     * Pages have their own permission, separate from articles.
     *
     * A member with only the articles section does not see برگه‌ها in the menu and gets
     * 403 on the pages list. Granting the pages section opens the list and the menu link.
     */
    public function test_the_pages_section_needs_its_own_permission(): void
    {
        $this->owner();
        $member = User::factory()->create([
            'username' => 'member_user',
            'email' => 'member@example.com',
            'phone' => '09120000002',
        ]);
        $member->grant([Section::ARTICLES]);
        $token = $this->token($member);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles')
            ->assertOk()
            ->assertDontSee('/admin/pages');

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/pages')
            ->assertForbidden();

        $member->grant([Section::ARTICLES, Section::PAGES]);
        // The test app keeps the signed-in user object, with its old permissions, between requests.
        $this->app['auth']->forgetGuards();

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/pages')
            ->assertOk()
            ->assertSee('/admin/pages', false);
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
