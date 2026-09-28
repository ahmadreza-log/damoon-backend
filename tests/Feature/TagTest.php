<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Filament\Resources\Tags\Pages\CreateTag;
use App\Filament\Resources\Tags\Pages\EditTag;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Models\Article;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Support\Library;
use App\Support\Sizes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the tags page, which sits under articles in the panel menu.
 *
 * Tags work exactly like categories: a parent tree, a slug, a description, sidebar
 * banners, and questions. The tests check the form and the saved record, the loop
 * guard on the parent field, what deleting a tag does to its articles, children, and
 * banner files, and that section access protects the page.
 *
 * Extending:
 * - CategoryTest mirrors this class because both models share the Taxonomy trait; change both together.
 */
class TagTest extends TestCase
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
     * The tags page is linked under articles and its form saves every field.
     *
     * The create page must show name, slug, parent tag, description, sidebar banners,
     * and questions. Saving builds the slug from the name, keeps the parent, description,
     * and questions, and stores a banner that points at a library picture. The banner
     * gets its WebP sizes, the media library names it as a tag banner, and the list
     * shows the new tag with its parent.
     */
    public function test_tags_sit_under_articles_and_the_form_has_every_field(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Tag::FOLDER, UploadedFile::fake()->image('banner.jpg', 600, 1200), 'banner.jpg');
        $owner = $this->owner();
        $token = $this->token($owner);
        $parent = Tag::query()->create(['name' => 'فناوری']);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles')
            ->assertOk()
            ->assertSee('برچسب‌ها')
            ->assertSee('/admin/articles/tags', false);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/tags/create')
            ->assertOk()
            ->assertSee('نام')
            ->assertSee('نامک')
            ->assertSee('برچسب مادر')
            ->assertSee('توضیح')
            ->assertSee('بنرهای سایدبار')
            ->assertSee('سوالات متداول');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateTag::class)
            ->fillForm([
                'name' => 'هوش مصنوعی',
                'parent_id' => $parent->getKey(),
                'description' => 'نوشته‌های هوش مصنوعی',
                'banners' => [
                    [
                        'image' => Tag::FOLDER.'/banner.jpg',
                        'title' => 'بنر نمونه',
                        'link' => 'https://example.com/offer',
                    ],
                ],
                'questions' => [
                    ['question' => 'این برچسب چیست؟', 'answer' => 'برچسب هوش مصنوعی.'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tag = Tag::query()->where('name', 'هوش مصنوعی')->first();

        $this->assertNotNull($tag);
        $this->assertSame('هوش-مصنوعی', $tag->slug);
        $this->assertSame($parent->getKey(), $tag->parent_id);
        $this->assertSame('نوشته‌های هوش مصنوعی', $tag->description);
        $this->assertSame([['question' => 'این برچسب چیست؟', 'answer' => 'برچسب هوش مصنوعی.']], $tag->questions);
        $this->assertCount(1, $tag->banners);
        $this->assertSame('بنر نمونه', $tag->banners[0]['title']);
        $this->assertSame('https://example.com/offer', $tag->banners[0]['link']);
        $this->assertSame(Tag::FOLDER.'/banner.jpg', $tag->banners[0]['image']);
        $disk->assertExists(Sizes::path($tag->banners[0]['image'], 'small'));

        $row = collect(Library::rows())->firstWhere('path', $tag->banners[0]['image']);
        $this->assertSame('بنر برچسب', $row['place']);
        $this->assertSame('بنر برچسب هوش مصنوعی', $row['usage']);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/tags')
            ->assertOk()
            ->assertSee('هوش مصنوعی')
            ->assertSee('فناوری');
    }

    /**
     * The parent field refuses a choice that would make a loop.
     *
     * family() must return the tag and all of its descendants. Choosing a grandchild
     * as the parent fails validation and leaves the parent unchanged. Choosing an
     * unrelated tag works, and clearing the slug on edit keeps the old slug.
     */
    public function test_a_tag_cannot_be_placed_under_itself_or_its_children(): void
    {
        $owner = $this->owner();
        $token = $this->token($owner);
        $root = Tag::query()->create(['name' => 'ریشه']);
        $child = Tag::query()->create(['name' => 'فرزند', 'parent_id' => $root->getKey()]);
        $grandchild = Tag::query()->create(['name' => 'نوه', 'parent_id' => $child->getKey()]);
        $other = Tag::query()->create(['name' => 'دیگر']);

        $this->assertEqualsCanonicalizing(
            [$root->getKey(), $child->getKey(), $grandchild->getKey()],
            $root->family(),
        );

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/tags/'.$root->getKey().'/edit')
            ->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditTag::class, ['record' => $root->getKey()])
            ->fillForm(['parent_id' => $grandchild->getKey()])
            ->call('save')
            ->assertHasFormErrors(['parent_id']);

        $this->assertNull($root->refresh()->parent_id);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditTag::class, ['record' => $root->getKey()])
            ->fillForm(['parent_id' => $other->getKey(), 'slug' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($other->getKey(), $root->refresh()->parent_id);
        $this->assertSame('ریشه', $root->slug);
    }

    /**
     * Deleting a tag only removes the tag itself.
     *
     * First, deleting one banner picture from the media library must remove that banner
     * from the tag. Then deleting the tag from the list must leave its child as a
     * top-level tag, keep the article but detach it from the deleted tag only, and keep
     * the remaining banner file and its sizes in the library.
     */
    public function test_deleting_a_tag_frees_its_articles_and_children_and_keeps_banner_files(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Tag::FOLDER, UploadedFile::fake()->image('side.jpg', 300, 600), 'side.jpg');
        $disk->putFileAs(Tag::FOLDER, UploadedFile::fake()->image('other.jpg', 300, 600), 'other.jpg');
        $owner = $this->owner();
        $token = $this->token($owner);

        $tag = Tag::query()->create([
            'name' => 'اخبار',
            'banners' => [
                ['image' => Tag::FOLDER.'/side.jpg', 'title' => 'یک', 'link' => null],
                ['image' => Tag::FOLDER.'/other.jpg', 'title' => 'دو', 'link' => null],
            ],
        ]);
        $child = Tag::query()->create(['name' => 'اخبار داخلی', 'parent_id' => $tag->getKey()]);
        $article = Article::query()->create([
            'title' => 'خبر',
            'content' => '<p>متن</p>',
            'author_id' => $owner->getKey(),
            'published_at' => now(),
        ]);
        $article->tags()->attach([$tag->getKey(), $child->getKey()]);

        Library::drop(Tag::FOLDER.'/other.jpg');
        $this->assertSame([Tag::FOLDER.'/side.jpg'], Tag::pictures($tag->refresh()->banners));

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/tags')
            ->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ListTags::class)
            ->assertTableActionExists('edit')
            ->callTableAction('delete', $tag);

        $this->assertNull(Tag::query()->find($tag->getKey()));
        $this->assertNull($child->refresh()->parent_id);
        $this->assertNotNull(Article::query()->find($article->getKey()));
        $this->assertSame([$child->getKey()], $article->tags()->pluck('tags.id')->all());
        $disk->assertExists(Tag::FOLDER.'/side.jpg');
        $disk->assertExists(Sizes::path(Tag::FOLDER.'/side.jpg', 'thumb'));
    }

    /**
     * A staff member without the articles section gets 403 on the tags page.
     *
     * The owner is created first so the member is not the first user and does not
     * receive the owner role automatically.
     */
    public function test_a_member_without_articles_cannot_open_tags(): void
    {
        $this->owner();
        $member = User::factory()->create([
            'username' => 'member_user',
            'email' => 'member@example.com',
            'phone' => '09120000002',
        ]);

        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($member))
            ->get('/admin/articles/tags')
            ->assertForbidden();
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
