<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Support\Library;
use App\Support\Sizes;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the articles page in the panel and the Article model behind it.
 *
 * The tests sign in as the owner with a real Sanctum panel cookie, then drive the
 * Filament pages through Livewire: the create form and every field on it, editing,
 * deleting from the edit page and the list, slug building, the JSON body with its
 * code blocks, body pictures in the media library, and the category and tag fields.
 *
 * Extending:
 * - A new article field needs an assertFormFieldExists and a value in the create test.
 * - Tests that store files call Storage::fake('public') first so nothing reaches the real disk.
 */
class ArticleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Marks the site as installed.
     *
     * Without a settings row with installed_at, EnsureInstalled redirects every panel
     * request to /install and no panel page could be tested.
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
     * Walks one article through its whole life in the panel.
     *
     * The create page must show every Persian label and field. The form is filled
     * with a JSON body holding a paragraph and a code block, a cover and gallery picked
     * from files already in the library, two categories, a tag, two related articles,
     * two related products, SEO text, and a question. The saved record must hold each
     * value, with content stored as JSON and rendered back to HTML. The title is then
     * edited, the list shows the new title, and deleting the article must keep the cover
     * and gallery files, because files belong to the media library, not the article.
     */
    public function test_an_article_can_be_created_edited_and_deleted(): void
    {
        $disk = Storage::fake('public');

        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $disk->putFileAs('articles/covers', $this->picture('cover.png'), 'cover.png');
        $disk->putFileAs('articles/gallery', $this->picture('gallery.png'), 'gallery.png');
        $category = Category::query()->create(['name' => 'خبر']);
        $second = Category::query()->create(['name' => 'آموزش']);
        $tag = Tag::query()->create(['name' => 'دامون']);
        $product = Product::query()->create(['title' => 'نهال گردو']);
        $seedling = Product::query()->create(['title' => 'نهال بادام']);
        $other = Article::query()->create([
            'title' => 'نوشته دیگر',
            'content' => '<p>دیگر</p>',
            'author_id' => $owner->getKey(),
            'published_at' => now(),
        ]);
        $another = Article::query()->create([
            'title' => 'نوشته سوم',
            'content' => '<p>سوم</p>',
            'author_id' => $owner->getKey(),
            'published_at' => now(),
        ]);
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/create')
            ->assertOk()
            ->assertSee('عنوان')
            ->assertSee('نامک')
            ->assertSee('محتوا')
            ->assertSee('تصویر شاخص')
            ->assertSee('دسته‌بندی')
            ->assertSee('برچسب')
            ->assertSee('نویسنده')
            ->assertSee('تاریخ انتشار')
            ->assertSee('اجازه به ارسال دیدگاه')
            ->assertSee('سئو')
            ->assertSee('سوالات متداول')
            ->assertSee('گالری تصاویر')
            ->assertSee('مقالات مرتبط')
            ->assertSee('محصولات مرتبط');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateArticle::class)
            ->assertFormFieldExists('title')
            ->assertFormFieldExists('slug')
            ->assertFormFieldExists('content')
            ->assertFormFieldExists('cover')
            ->assertFormFieldExists('gallery')
            ->assertFormFieldExists('categories')
            ->assertFormFieldExists('tags')
            ->assertFormFieldExists('author_id')
            ->assertFormFieldExists('published_at')
            ->assertFormSet(['commentable' => true])
            ->assertFormFieldExists('seo_meta.title')
            ->assertFormFieldExists('seo_meta.description')
            ->assertFormFieldExists('seo_meta.og_image')
            ->assertFormFieldExists('questions')
            ->assertFormFieldExists('related')
            ->assertFormFieldExists('products')
            ->fillForm([
                'title' => 'نوشته نمونه',
                'slug' => 'نمونه',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'متن نوشته']]],
                        ['type' => 'customBlock', 'attrs' => ['id' => 'code', 'config' => ['language' => 'html', 'code' => '<div class="box">سلام</div>']]],
                    ],
                ],
                'cover' => 'articles/covers/cover.png',
                'gallery' => ['articles/gallery/gallery.png'],
                'categories' => [$category->getKey(), $second->getKey()],
                'tags' => [$tag->getKey()],
                'author_id' => $owner->getKey(),
                'published_at' => now()->toDateTimeString(),
                'commentable' => false,
                'seo_meta' => [
                    'title' => 'عنوان سئو',
                    'description' => 'توضیح سئو',
                    'og_image' => 'articles/covers/cover.png',
                ],
                'questions' => [
                    ['question' => 'این چیست؟', 'answer' => 'یک نوشته است.'],
                ],
                'related' => [$other->getKey(), $another->getKey()],
                'products' => [$product->getKey(), $seedling->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::query()->where('slug', 'نمونه')->first();

        $this->assertNotNull($article);
        $this->assertSame('نوشته نمونه', $article->title);
        $this->assertSame('doc', $article->content['type']);
        $this->assertSame('متن نوشته', $article->content['content'][0]['content'][0]['text']);
        $this->assertSame('customBlock', $article->content['content'][1]['type']);
        $this->assertSame('code', $article->content['content'][1]['attrs']['id']);
        $this->assertSame('<div class="box">سلام</div>', $article->content['content'][1]['attrs']['config']['code']);
        $this->assertIsArray(json_decode((string) DB::table('articles')->where('id', $article->getKey())->value('content'), true));
        $this->assertStringContainsString('<p>متن نوشته</p>', $article->html());
        $this->assertStringContainsString('<div class="box">سلام</div>', $article->html());
        $this->assertEqualsCanonicalizing([$category->getKey(), $second->getKey()], $article->categories->modelKeys());
        $this->assertSame($owner->getKey(), $article->author_id);
        $this->assertFalse($article->commentable);
        $this->assertSame('عنوان سئو', $article->seoMeta->title);
        $this->assertSame('توضیح سئو', $article->seoMeta->description);
        $this->assertSame('/storage/articles/covers/cover.png', $article->seoMeta->og_image);
        $this->assertSame([['question' => 'این چیست؟', 'answer' => 'یک نوشته است.']], $article->questions);
        $this->assertTrue($article->tags->contains($tag));
        $this->assertEqualsCanonicalizing([$other->getKey(), $another->getKey()], $article->related->modelKeys());
        $this->assertEqualsCanonicalizing([$product->getKey(), $seedling->getKey()], $article->products->modelKeys());
        $this->assertSame('articles/covers/cover.png', $article->cover);
        $this->assertSame(['articles/gallery/gallery.png'], $article->gallery);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/'.$article->getKey().'/edit')
            ->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditArticle::class, ['record' => $article->getKey()])
            ->fillForm([
                'title' => 'نوشته ویرایش‌شده',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $article->refresh();
        $this->assertSame('نوشته ویرایش‌شده', $article->title);

        $cover = $article->cover;
        $gallery = $article->gallery[0];

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles')
            ->assertOk()
            ->assertSee('نوشته ویرایش‌شده');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditArticle::class, ['record' => $article->getKey()])
            ->callAction('delete');

        $this->assertNull(Article::query()->find($article->getKey()));
        $disk->assertExists($cover);
        $disk->assertExists($gallery);
    }

    /**
     * An article saved without a slug gets one from its title.
     *
     * Spaces become dashes and Persian letters are kept. When the slug is already
     * taken, the next free number is appended, so two articles with the same title
     * get "title" and "title-2".
     */
    public function test_a_blank_slug_is_built_from_the_title(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);

        $first = Article::query()->create([
            'title' => 'نوشته تکراری',
            'content' => '<p>اول</p>',
            'author_id' => $owner->getKey(),
            'published_at' => now(),
        ]);
        $second = Article::query()->create([
            'title' => 'نوشته تکراری',
            'content' => '<p>دوم</p>',
            'author_id' => $owner->getKey(),
            'published_at' => now(),
        ]);

        $this->assertSame('نوشته-تکراری', $first->slug);
        $this->assertSame('نوشته-تکراری-2', $second->slug);
    }

    /**
     * The articles list has a create button, and edit and delete actions on each row.
     *
     * Deleting from the row action must remove the article from the database.
     */
    public function test_the_list_offers_create_and_delete(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $article = Article::query()->create([
            'title' => 'برای حذف',
            'content' => '<p>حذف</p>',
            'author_id' => $owner->getKey(),
            'published_at' => now(),
        ]);
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles')
            ->assertOk();

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ListArticles::class)
            ->assertActionExists('create')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('delete', record: $article)
            ->callTableAction('delete', $article);

        $this->assertNull(Article::query()->find($article->getKey()));
    }

    /**
     * The body is always stored as the rich editor's JSON document.
     *
     * An HTML string, as older articles and imports provide, is converted to JSON on
     * save and still renders back to the same markup. A code block renders its code
     * untouched: CSS inside a style tag and JavaScript inside a script tag.
     */
    public function test_html_content_is_stored_as_json_and_code_blocks_render_as_written(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);

        $old = Article::query()->create([
            'title' => 'نوشته قدیمی',
            'content' => '<h2>تیتر</h2><p>متن <strong>پررنگ</strong></p>',
            'author_id' => $owner->getKey(),
            'published_at' => now(),
        ]);

        $stored = json_decode((string) DB::table('articles')->where('id', $old->getKey())->value('content'), true);
        $this->assertSame('doc', $stored['type']);
        $this->assertSame('heading', $stored['content'][0]['type']);
        $this->assertSame('تیتر', $stored['content'][0]['content'][0]['text']);
        $this->assertStringContainsString('<strong>پررنگ</strong>', $old->refresh()->html());

        $coded = Article::query()->create([
            'title' => 'نوشته با کد',
            'content' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'customBlock', 'attrs' => ['id' => 'code', 'config' => ['language' => 'css', 'code' => '.box{color:red}']]],
                    ['type' => 'customBlock', 'attrs' => ['id' => 'code', 'config' => ['language' => 'javascript', 'code' => 'console.log(1)']]],
                ],
            ],
            'author_id' => $owner->getKey(),
            'published_at' => now(),
        ]);

        $html = $coded->refresh()->html();
        $this->assertStringContainsString('<style>.box{color:red}</style>', $html);
        $this->assertStringContainsString('<script>console.log(1)</script>', $html);
    }

    /**
     * The body editor has every toolbar button the content team asked for.
     *
     * Beyond the buttons, the editor must store JSON, allow custom text colours and
     * resizable images, and save attached pictures on the public disk under
     * Article::FOLDER so they appear in the media library.
     */
    public function test_the_body_editor_offers_every_tool(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/create')
            ->assertOk()
            ->assertSee('تیتر');

        $tools = [
            'bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'code', 'link',
            'textColor', 'highlight', 'small', 'lead', 'clearFormatting',
            'paragraph', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'alignStart', 'alignCenter', 'alignEnd', 'alignJustify',
            'blockquote', 'codeBlock', 'bulletList', 'orderedList', 'horizontalRule', 'details',
            'table', 'grid', 'gridDelete', 'attachFiles', 'customBlocks', 'undo', 'redo',
        ];

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateArticle::class)
            ->assertFormFieldExists('content', function (RichEditor $field) use ($tools): bool {
                foreach ($tools as $tool) {
                    if (! $field->hasToolbarButton($tool)) {
                        return false;
                    }
                }

                return $field->isJson()
                    && $field->hasCustomTextColors()
                    && $field->hasResizableImages()
                    && $field->getFileAttachmentsDiskName() === 'public'
                    && $field->getFileAttachmentsDirectory() === Article::FOLDER;
            });
    }

    /**
     * Pictures placed inside the body behave like every other library file.
     *
     * Saving the article builds their WebP sizes. The media library names where each
     * picture is used ("تصویر محتوا" in the body of this article). Deleting a picture
     * from the library removes its image node from the body. Deleting the article
     * leaves the remaining picture and its sizes on disk, now marked as unused.
     */
    public function test_body_pictures_get_sizes_show_their_use_and_stay_in_the_library(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Article::FOLDER, UploadedFile::fake()->image('inside.jpg', 1200, 600), 'inside.jpg');
        $disk->putFileAs(Article::FOLDER, UploadedFile::fake()->image('second.jpg', 800, 400), 'second.jpg');
        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $picture = fn (string $path): array => ['type' => 'image', 'attrs' => ['id' => $path, 'src' => '/storage/'.$path, 'alt' => 'تصویر']];

        $article = Article::query()->create([
            'title' => 'نوشته تصویری',
            'content' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'متن']]],
                    $picture(Article::FOLDER.'/inside.jpg'),
                    $picture(Article::FOLDER.'/second.jpg'),
                ],
            ],
            'author_id' => $owner->getKey(),
            'published_at' => now(),
        ]);

        $disk->assertExists(Sizes::path(Article::FOLDER.'/inside.jpg', 'medium'));
        $this->assertStringContainsString('inside.jpg', $article->html());

        $row = collect(Library::rows())->firstWhere('path', Article::FOLDER.'/second.jpg');
        $this->assertSame('تصویر محتوا', $row['place']);
        $this->assertSame('محتوای نوشته تصویری', $row['usage']);

        Library::drop(Article::FOLDER.'/second.jpg');

        $this->assertSame([Article::FOLDER.'/inside.jpg'], Article::images($article->refresh()->content));
        $disk->assertMissing(Article::FOLDER.'/second.jpg');

        $article->delete();

        $disk->assertExists(Article::FOLDER.'/inside.jpg');
        $disk->assertExists(Sizes::path(Article::FOLDER.'/inside.jpg', 'medium'));
        $this->assertSame('بدون استفاده', collect(Library::rows())->firstWhere('path', Article::FOLDER.'/inside.jpg')['usage']);
    }

    /**
     * The category and tag fields are tied to the categories and tags pages.
     *
     * Both fields are multi-select. Each one links to its management page, and category
     * options show the full parent trail, such as "فناوری › هوش مصنوعی". Creating a
     * category or tag from the field's popup uses the same form as its page, saves the
     * new record with its slug and parent, and adds it to the selection next to the
     * items already chosen. The saved article must be attached to every chosen category and tag.
     */
    public function test_category_and_tag_fields_use_the_category_and_tag_pages(): void
    {
        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $token = app(AccessTokens::class)->issue($owner, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);
        $parent = Category::query()->create(['name' => 'فناوری']);
        $child = Category::query()->create(['name' => 'هوش مصنوعی', 'parent_id' => $parent->getKey()]);
        $topic = Tag::query()->create(['name' => 'برنامه‌نویسی']);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/create')
            ->assertOk()
            ->assertSee('مدیریت دسته‌بندی‌ها')
            ->assertSee('مدیریت برچسب‌ها')
            ->assertSee('/admin/articles/categories', false)
            ->assertSee('/admin/articles/tags', false);

        $page = Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(CreateArticle::class)
            ->assertFormFieldExists('categories', fn (Select $field): bool => $field->isMultiple() && ($field->getOptions()[$child->getKey()] ?? null) === 'فناوری › هوش مصنوعی')
            ->assertFormFieldExists('tags', fn (Select $field): bool => $field->isMultiple())
            ->fillForm(['categories' => [$child->getKey()]])
            ->callAction(TestAction::make('createOption')->schemaComponent('categories'), data: [
                'name' => 'یادگیری ماشین',
                'parent_id' => $child->getKey(),
                'description' => 'زیرشاخهٔ هوش مصنوعی',
                'questions' => [['question' => 'چیست؟', 'answer' => 'یک دسته.']],
            ])
            ->assertHasNoFormErrors();

        $category = Category::query()->where('name', 'یادگیری ماشین')->first();

        $this->assertNotNull($category);
        $this->assertSame('یادگیری-ماشین', $category->slug);
        $this->assertSame($child->getKey(), $category->parent_id);
        $this->assertSame('زیرشاخهٔ هوش مصنوعی', $category->description);
        $this->assertSame('فناوری › هوش مصنوعی › یادگیری ماشین', $category->trail());
        $page->assertFormSet(['categories' => [$child->getKey(), $category->getKey()]]);

        $page->fillForm(['tags' => [$topic->getKey()]]);

        $page->callAction(TestAction::make('createOption')->schemaComponent('tags'), data: [
            'name' => 'لاراول',
            'parent_id' => $topic->getKey(),
        ])->assertHasNoFormErrors();

        $tag = Tag::query()->where('name', 'لاراول')->first();

        $this->assertNotNull($tag);
        $this->assertSame('لاراول', $tag->slug);
        $this->assertSame($topic->getKey(), $tag->parent_id);
        $page->assertFormSet(['tags' => [$topic->getKey(), $tag->getKey()]]);

        $page->fillForm([
            'title' => 'نوشته چنددسته',
            'content' => '<p>متن</p>',
            'author_id' => $owner->getKey(),
            'published_at' => now()->toDateTimeString(),
        ])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::query()->where('title', 'نوشته چنددسته')->first();

        $this->assertNotNull($article);
        $this->assertEqualsCanonicalizing([$child->getKey(), $category->getKey()], $article->categories()->pluck('categories.id')->all());
        $this->assertEqualsCanonicalizing([$topic->getKey(), $tag->getKey()], $article->tags()->pluck('tags.id')->all());
        $this->assertSame(1, $category->articles()->count());
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
