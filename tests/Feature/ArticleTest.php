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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->create([
            'title' => 'دامون',
            'description' => 'سامانه مدیریت محتوا',
            'installed_at' => now(),
        ]);
    }

    public function test_an_article_can_be_created_edited_and_deleted(): void
    {
        $disk = Storage::fake('public');

        $owner = User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
        $category = Category::query()->create(['name' => 'خبر']);
        $tag = Tag::query()->create(['name' => 'دامون']);
        $product = Product::query()->create(['title' => 'نهال گردو']);
        $other = Article::query()->create([
            'title' => 'نوشته دیگر',
            'content' => '<p>دیگر</p>',
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
            ->assertFormFieldExists('category_id')
            ->assertFormFieldExists('tags')
            ->assertFormFieldExists('author_id')
            ->assertFormFieldExists('published_at')
            ->assertFormFieldExists('seo_title')
            ->assertFormFieldExists('seo_description')
            ->assertFormFieldExists('questions')
            ->assertFormFieldExists('related')
            ->assertFormFieldExists('products')
            ->fillForm([
                'title' => 'نوشته نمونه',
                'slug' => 'نمونه',
                'content' => '<p>متن نوشته</p>',
                'cover' => $this->picture('cover.png'),
                'gallery' => [$this->picture('gallery.png')],
                'category_id' => $category->getKey(),
                'tags' => [$tag->getKey()],
                'author_id' => $owner->getKey(),
                'published_at' => now()->toDateTimeString(),
                'seo_title' => 'عنوان سئو',
                'seo_description' => 'توضیح سئو',
                'questions' => [
                    ['question' => 'این چیست؟', 'answer' => 'یک نوشته است.'],
                ],
                'related' => [$other->getKey()],
                'products' => [$product->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::query()->where('slug', 'نمونه')->first();

        $this->assertNotNull($article);
        $this->assertSame('نوشته نمونه', $article->title);
        $this->assertSame('<p>متن نوشته</p>', $article->content);
        $this->assertSame($category->getKey(), $article->category_id);
        $this->assertSame($owner->getKey(), $article->author_id);
        $this->assertSame('عنوان سئو', $article->seo_title);
        $this->assertSame([['question' => 'این چیست؟', 'answer' => 'یک نوشته است.']], $article->questions);
        $this->assertTrue($article->tags->contains($tag));
        $this->assertTrue($article->related->contains($other));
        $this->assertTrue($article->products->contains($product));
        $this->assertIsString($article->cover);
        $this->assertStringStartsWith('articles/covers/', $article->cover);
        $disk->assertExists($article->cover);
        $this->assertIsArray($article->gallery);
        $this->assertNotEmpty($article->gallery);
        $disk->assertExists($article->gallery[0]);

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
        $disk->assertMissing($cover);
        $disk->assertMissing($gallery);
    }

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
