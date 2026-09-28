<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Support\Library;
use App\Support\Sizes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the categories page, which sits under articles in the panel menu.
 *
 * The tests check the form fields and the saved record, the rule that a category
 * cannot be moved under itself or its own descendants, what deleting a category does
 * to its articles, children, and banner files, and that section access protects the page.
 *
 * Extending:
 * - TagTest mirrors this class because tags share the Taxonomy trait; change both together.
 */
class CategoryTest extends TestCase
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
     * The categories page is linked under articles and its form saves every field.
     *
     * The create page must show name, slug, parent, description, sidebar banners, and
     * questions. Saving builds the slug from the name, keeps the parent, description,
     * and questions, and stores a banner that points at a library picture. The banner
     * gets its WebP sizes, the media library names it as a category banner, and the
     * list shows the new category with its parent.
     */
    public function test_categories_sit_under_articles_and_the_form_has_every_field(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Category::FOLDER, UploadedFile::fake()->image('banner.jpg', 600, 1200), 'banner.jpg');
        $owner = $this->owner();
        $token = $this->token($owner);
        $parent = Category::query()->create(['name' => 'فناوری']);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles')
            ->assertOk()
            ->assertSee('دسته‌بندی‌ها')
            ->assertSee('/admin/articles/categories', false);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/categories/create')
            ->assertOk()
            ->assertSee('نام')
            ->assertSee('نامک')
            ->assertSee('دستهٔ مادر')
            ->assertSee('توضیح')
            ->assertSee('بنرهای سایدبار')
            ->assertSee('سوالات متداول');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateCategory::class)
            ->fillForm([
                'name' => 'هوش مصنوعی',
                'parent_id' => $parent->getKey(),
                'description' => 'نوشته‌های هوش مصنوعی',
                'banners' => [
                    [
                        'image' => Category::FOLDER.'/banner.jpg',
                        'title' => 'بنر نمونه',
                        'link' => 'https://example.com/offer',
                    ],
                ],
                'questions' => [
                    ['question' => 'این دسته چیست؟', 'answer' => 'دستهٔ هوش مصنوعی.'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::query()->where('name', 'هوش مصنوعی')->first();

        $this->assertNotNull($category);
        $this->assertSame('هوش-مصنوعی', $category->slug);
        $this->assertSame($parent->getKey(), $category->parent_id);
        $this->assertSame('نوشته‌های هوش مصنوعی', $category->description);
        $this->assertSame([['question' => 'این دسته چیست؟', 'answer' => 'دستهٔ هوش مصنوعی.']], $category->questions);
        $this->assertCount(1, $category->banners);
        $this->assertSame('بنر نمونه', $category->banners[0]['title']);
        $this->assertSame('https://example.com/offer', $category->banners[0]['link']);
        $this->assertSame(Category::FOLDER.'/banner.jpg', $category->banners[0]['image']);
        $disk->assertExists(Sizes::path($category->banners[0]['image'], 'small'));

        $row = collect(Library::rows())->firstWhere('path', $category->banners[0]['image']);
        $this->assertSame('بنر دسته‌بندی', $row['place']);
        $this->assertSame('بنر دسته‌بندی هوش مصنوعی', $row['usage']);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/categories')
            ->assertOk()
            ->assertSee('هوش مصنوعی')
            ->assertSee('فناوری');
    }

    /**
     * The parent field refuses a choice that would make a loop.
     *
     * family() must return the category and all of its descendants. Choosing a
     * grandchild as the parent fails validation and leaves the parent unchanged.
     * Choosing an unrelated category works, and clearing the slug on edit keeps the
     * old slug rather than building a new one.
     */
    public function test_a_category_cannot_be_placed_under_itself_or_its_children(): void
    {
        $owner = $this->owner();
        $token = $this->token($owner);
        $root = Category::query()->create(['name' => 'ریشه']);
        $child = Category::query()->create(['name' => 'فرزند', 'parent_id' => $root->getKey()]);
        $grandchild = Category::query()->create(['name' => 'نوه', 'parent_id' => $child->getKey()]);
        $other = Category::query()->create(['name' => 'دیگر']);

        $this->assertEqualsCanonicalizing(
            [$root->getKey(), $child->getKey(), $grandchild->getKey()],
            $root->family(),
        );

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/categories/'.$root->getKey().'/edit')
            ->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditCategory::class, ['record' => $root->getKey()])
            ->fillForm(['parent_id' => $grandchild->getKey()])
            ->call('save')
            ->assertHasFormErrors(['parent_id']);

        $this->assertNull($root->refresh()->parent_id);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditCategory::class, ['record' => $root->getKey()])
            ->fillForm(['parent_id' => $other->getKey(), 'slug' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($other->getKey(), $root->refresh()->parent_id);
        $this->assertSame('ریشه', $root->slug);
    }

    /**
     * Deleting a category only removes the category itself.
     *
     * First, deleting one banner picture from the media library must remove that banner
     * from the category. Then deleting the category from the list must leave its child
     * as a top-level category, keep the article but detach it from the deleted category
     * only, and keep the remaining banner file and its sizes in the library.
     */
    public function test_deleting_a_category_frees_its_articles_and_children_and_keeps_banner_files(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Category::FOLDER, UploadedFile::fake()->image('side.jpg', 300, 600), 'side.jpg');
        $disk->putFileAs(Category::FOLDER, UploadedFile::fake()->image('other.jpg', 300, 600), 'other.jpg');
        $owner = $this->owner();
        $token = $this->token($owner);

        $category = Category::query()->create([
            'name' => 'اخبار',
            'banners' => [
                ['image' => Category::FOLDER.'/side.jpg', 'title' => 'یک', 'link' => null],
                ['image' => Category::FOLDER.'/other.jpg', 'title' => 'دو', 'link' => null],
            ],
        ]);
        $child = Category::query()->create(['name' => 'اخبار داخلی', 'parent_id' => $category->getKey()]);
        $article = Article::query()->create([
            'title' => 'خبر',
            'content' => '<p>متن</p>',
            'author_id' => $owner->getKey(),
            'published_at' => now(),
        ]);
        $article->categories()->attach([$category->getKey(), $child->getKey()]);

        Library::drop(Category::FOLDER.'/other.jpg');
        $this->assertSame([Category::FOLDER.'/side.jpg'], Category::pictures($category->refresh()->banners));

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/categories')
            ->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ListCategories::class)
            ->assertTableActionExists('edit')
            ->callTableAction('delete', $category);

        $this->assertNull(Category::query()->find($category->getKey()));
        $this->assertNull($child->refresh()->parent_id);
        $this->assertNotNull(Article::query()->find($article->getKey()));
        $this->assertSame([$child->getKey()], $article->categories()->pluck('categories.id')->all());
        $disk->assertExists(Category::FOLDER.'/side.jpg');
        $disk->assertExists(Sizes::path(Category::FOLDER.'/side.jpg', 'thumb'));
    }

    /**
     * A staff member without the articles section gets 403 on the categories page.
     *
     * The owner is created first so the member is not the first user and does not
     * receive the owner role automatically.
     */
    public function test_a_member_without_articles_cannot_open_categories(): void
    {
        $this->owner();
        $member = User::factory()->create([
            'username' => 'member_user',
            'email' => 'member@example.com',
            'phone' => '09120000002',
        ]);

        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($member))
            ->get('/admin/articles/categories')
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
