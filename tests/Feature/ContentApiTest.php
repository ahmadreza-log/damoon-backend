<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Page;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the public content API: articles, pages, categories, tags, and media under /v1.
 *
 * These routes need no token and send only published content. Pictures come with full
 * addresses and their sizes, and errors are JSON even when the request did not ask for it.
 *
 * Extending:
 * - A new content route gets a test here and a path in OpenApiTest.
 * - Tests that store files call Storage::fake('public') first so nothing reaches the real disk.
 */
class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The list sends published articles only, newest first, and filters by text, category, and tag.
     *
     * A category filter also brings articles from the categories under it. A per_page above
     * the limit is refused with a JSON 422 even without an Accept header.
     */
    public function test_article_list_is_published_only_and_filters(): void
    {
        $author = User::factory()->create();
        $parent = Category::query()->create(['name' => 'فناوری']);
        $child = Category::query()->create(['name' => 'هوش مصنوعی', 'parent_id' => $parent->getKey()]);
        $tag = Tag::query()->create(['name' => 'لاراول']);

        $old = Article::query()->create(['title' => 'نوشته قدیمی', 'author_id' => $author->getKey(), 'published_at' => now()->subDays(3)]);
        $new = Article::query()->create(['title' => 'مدل‌های زبانی', 'author_id' => $author->getKey(), 'published_at' => now()->subDay()]);
        $new->categories()->attach($child);
        $new->tags()->attach($tag);
        Article::query()->create(['title' => 'پیش‌نویس', 'author_id' => $author->getKey(), 'published_at' => now()->addWeek()]);
        Article::query()->create(['title' => 'آینده', 'author_id' => $author->getKey(), 'published_at' => now()->addDay()]);

        $this->get('/v1/articles')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', $new->slug)
            ->assertJsonPath('data.0.author', $author->getFilamentName())
            ->assertJsonPath('data.0.categories.0.slug', $child->slug)
            ->assertJsonPath('data.0.tags.0.slug', $tag->slug)
            ->assertJsonPath('data.1.slug', $old->slug)
            ->assertJsonPath('meta.total', 2);

        $this->get('/v1/articles?category='.urlencode((string) $parent->slug))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $new->getKey());

        $this->get('/v1/articles?tag='.urlencode((string) $tag->slug))->assertJsonCount(1, 'data');
        $this->get('/v1/articles?q='.urlencode('قدیمی'))->assertJsonPath('data.0.id', $old->getKey());
        $this->get('/v1/articles?category=missing')->assertOk()->assertJsonCount(0, 'data');

        $this->get('/v1/articles?per_page=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    /**
     * One article sends its body as a Tiptap JSON document, gallery pictures with sizes, questions, published related articles, and SEO.
     *
     * A library picture inside the body keeps its node and gains its full address and sizes.
     *
     * An article scheduled for later, and a slug that does not exist, get a JSON 404 with a Persian message.
     */
    public function test_article_detail_sends_the_full_article(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs('articles/gallery', $this->picture('one.png'), 'one.png');
        $disk->putFileAs(Article::FOLDER, $this->picture('inside.png'), 'inside.png');
        $author = User::factory()->create();

        $article = Article::query()->create([
            'title' => 'راهنمای دامون',
            'content' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'متن اصلی نوشته']]],
                    ['type' => 'image', 'attrs' => ['id' => Article::FOLDER.'/inside.png', 'src' => null, 'alt' => 'نمونه']],
                ],
            ],
            'author_id' => $author->getKey(),
            'published_at' => now()->subHour(),
            'gallery' => ['articles/gallery/one.png'],
            'questions' => [
                ['question' => 'دامون چیست؟', 'answer' => 'یک سامانه مدیریت محتوا'],
                ['question' => '', 'answer' => 'بدون سوال'],
            ],
            'commentable' => false,
        ]);
        $shown = Article::query()->create(['title' => 'مرتبط منتشرشده', 'author_id' => $author->getKey(), 'published_at' => now()->subHour()]);
        $draft = Article::query()->create(['title' => 'مرتبط پیش‌نویس', 'author_id' => $author->getKey(), 'published_at' => now()->addWeek()]);
        $article->related()->attach([$shown->getKey(), $draft->getKey()]);

        $response = $this->get('/v1/articles/'.urlencode((string) $article->slug))
            ->assertOk()
            ->assertJsonPath('data.title', 'راهنمای دامون')
            ->assertJsonPath('data.author', $author->getFilamentName())
            ->assertJsonPath('data.commentable', false)
            ->assertJsonPath('data.gallery.0.path', 'articles/gallery/one.png')
            ->assertJsonCount(1, 'data.questions')
            ->assertJsonPath('data.questions.0.question', 'دامون چیست؟')
            ->assertJsonCount(1, 'data.related')
            ->assertJsonPath('data.related.0.id', $shown->getKey())
            ->assertJsonMissingPath('data.author.email')
            ->assertJsonPath('data.content.type', 'doc')
            ->assertJsonPath('data.content.content.0.type', 'paragraph')
            ->assertJsonPath('data.content.content.0.content.0.text', 'متن اصلی نوشته')
            ->assertJsonPath('data.content.content.1.type', 'image')
            ->assertJsonPath('data.content.content.1.attrs.alt', 'نمونه')
            ->assertJsonPath('data.content.content.1.attrs.picture.path', Article::FOLDER.'/inside.png');

        $this->assertStringStartsWith('http://', (string) $response->json('data.content.content.1.attrs.src'));
        $this->assertStringStartsWith('http://', (string) $response->json('data.content.content.1.attrs.picture.sizes.large'));
        $this->assertStringStartsWith('http://', (string) $response->json('data.gallery.0.url'));
        $this->assertSame(['thumb', 'small', 'medium', 'large'], array_keys((array) $response->json('data.gallery.0.sizes')));
        $this->assertStringContainsString('راهنمای دامون', (string) $response->json('data.seo.title'));
        $this->assertStringContainsString('متن اصلی نوشته', (string) $response->json('data.seo.description'));

        $this->get('/v1/articles/'.urlencode((string) $draft->slug))
            ->assertNotFound()
            ->assertJsonPath('message', 'نوشته پیدا نشد.');
        $this->get('/v1/articles/missing')->assertNotFound();
    }

    /**
     * Pages list only published ones; one page sends its page builder design as JSON, with picture addresses, and as HTML and CSS.
     *
     * Children scheduled for later are left out, and such a page gets 404.
     */
    public function test_pages_send_design_and_children(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Page::DESIGNS, $this->picture('hero.png'), 'hero.png');
        $src = '/storage/'.Page::DESIGNS.'/hero.png';

        $about = Page::query()->create([
            'title' => 'درباره ما',
            'published_at' => now()->subDay(),
            'design' => [
                'pages' => [['frames' => [['component' => ['type' => 'wrapper', 'components' => [
                    ['tagName' => 'section', 'classes' => ['hero'], 'components' => [
                        ['type' => 'text', 'tagName' => 'h1', 'components' => [['type' => 'textnode', 'content' => 'خوش آمدید']]],
                        ['type' => 'image', 'attributes' => ['src' => $src, 'alt' => 'بنر']],
                    ]],
                ]]]]]],
                'styles' => [['selectors' => ['hero'], 'style' => ['background-image' => 'url("'.$src.'")', 'padding' => '40px']]],
            ],
            'markup' => '<section class="hero"><h1>خوش آمدید</h1><img src="'.$src.'" alt="بنر"></section>',
            'style' => '.hero{background-image:url("'.$src.'");}',
        ]);
        $team = Page::query()->create(['title' => 'تیم ما', 'parent_id' => $about->getKey(), 'position' => 1, 'published_at' => now()->subDay()]);
        $hidden = Page::query()->create(['title' => 'پنهان', 'parent_id' => $about->getKey(), 'published_at' => now()->addWeek()]);

        $this->get('/v1/pages')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.parent_id', $about->getKey());

        $response = $this->get('/v1/pages/'.urlencode((string) $about->slug))
            ->assertOk()
            ->assertJsonCount(1, 'data.design.components')
            ->assertJsonPath('data.design.components.0.tagName', 'section')
            ->assertJsonPath('data.design.components.0.components.0.components.0.content', 'خوش آمدید')
            ->assertJsonPath('data.design.components.0.components.1.type', 'image')
            ->assertJsonPath('data.design.components.0.components.1.attributes.alt', 'بنر')
            ->assertJsonPath('data.design.components.0.components.1.picture.path', Page::DESIGNS.'/hero.png')
            ->assertJsonPath('data.design.styles.0.style.padding', '40px')
            ->assertJsonPath('data.commentable', true)
            ->assertJsonPath('data.content', ['type' => 'doc', 'content' => []])
            ->assertJsonCount(1, 'data.children')
            ->assertJsonPath('data.children.0.id', $team->getKey())
            ->assertJsonPath('data.parent', null)
            ->assertJsonMissingPath('data.blocks');

        $full = url($src);
        $this->assertSame($full, $response->json('data.design.components.0.components.1.attributes.src'));
        $this->assertSame('url("'.$full.'")', $response->json('data.design.styles.0.style.background-image'));
        $this->assertStringContainsString('<img src="'.$full.'"', (string) $response->json('data.html'));
        $this->assertStringContainsString('url("'.$full.'")', (string) $response->json('data.css'));

        $this->get('/v1/pages/'.urlencode((string) $team->slug))
            ->assertOk()
            ->assertJsonPath('data.design', ['components' => [], 'styles' => []])
            ->assertJsonPath('data.html', '')
            ->assertJsonPath('data.parent.id', $about->getKey())
            ->assertJsonPath('data.trail', 'درباره ما › تیم ما');

        $this->get('/v1/pages/'.urlencode((string) $hidden->slug))
            ->assertNotFound()
            ->assertJsonPath('message', 'برگه پیدا نشد.');
    }

    /**
     * A slider sends its slides with their texts, link, and a full picture address with the built sizes.
     */
    public function test_slider_sends_its_slides(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Page::DESIGNS, $this->picture('slide.png'), 'slide.png');
        $src = '/storage/'.Page::DESIGNS.'/slide.png';

        $page = Page::query()->create([
            'title' => 'خانه',
            'published_at' => now()->subDay(),
            'design' => [
                'pages' => [['frames' => [['component' => ['type' => 'wrapper', 'components' => [
                    ['type' => 'slider', 'attributes' => ['data-slider' => '', 'data-autoplay' => '5'], 'slides' => [
                        ['title' => 'بهار', 'text' => 'تخفیف فصل', 'label' => 'خرید', 'href' => '/shop', 'src' => $src],
                        ['title' => 'بی‌تصویر', 'text' => '', 'label' => '', 'href' => '', 'src' => ''],
                    ]],
                ]]]]]],
            ],
        ]);

        $response = $this->get('/v1/pages/'.urlencode((string) $page->slug))
            ->assertOk()
            ->assertJsonPath('data.design.components.0.type', 'slider')
            ->assertJsonCount(2, 'data.design.components.0.slides')
            ->assertJsonPath('data.design.components.0.slides.0.title', 'بهار')
            ->assertJsonPath('data.design.components.0.slides.0.href', '/shop')
            ->assertJsonPath('data.design.components.0.slides.0.picture.path', Page::DESIGNS.'/slide.png')
            ->assertJsonPath('data.design.components.0.slides.1.src', '')
            ->assertJsonPath('data.design.components.0.slides.1.picture', null);

        $this->assertSame(url($src), $response->json('data.design.components.0.slides.0.src'));
    }

    /**
     * Categories and tags list with published article counts; one sends banners, questions, children, and its trail.
     */
    public function test_categories_and_tags_send_their_details(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs(Category::FOLDER, $this->picture('side.png'), 'side.png');
        $author = User::factory()->create();

        $parent = Category::query()->create([
            'name' => 'فناوری',
            'description' => 'همه چیز درباره فناوری',
            'banners' => [['image' => Category::FOLDER.'/side.png', 'title' => 'دوره جدید', 'link' => '/courses']],
            'questions' => [['question' => 'چه می‌خوانیم؟', 'answer' => 'فناوری']],
        ]);
        $child = Category::query()->create(['name' => 'موبایل', 'parent_id' => $parent->getKey()]);
        $tag = Tag::query()->create(['name' => 'اندروید']);

        $published = Article::query()->create(['title' => 'منتشرشده', 'author_id' => $author->getKey(), 'published_at' => now()->subHour()]);
        $draft = Article::query()->create(['title' => 'پیش‌نویس', 'author_id' => $author->getKey(), 'published_at' => now()->addWeek()]);
        $parent->articles()->attach([$published->getKey(), $draft->getKey()]);
        $tag->articles()->attach($published);

        $this->get('/v1/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['slug' => $parent->slug, 'articles_count' => 1]);

        $response = $this->get('/v1/categories/'.urlencode((string) $parent->slug))
            ->assertOk()
            ->assertJsonPath('data.description', 'همه چیز درباره فناوری')
            ->assertJsonPath('data.articles_count', 1)
            ->assertJsonPath('data.banners.0.title', 'دوره جدید')
            ->assertJsonPath('data.banners.0.link', '/courses')
            ->assertJsonPath('data.banners.0.image.path', Category::FOLDER.'/side.png')
            ->assertJsonPath('data.questions.0.question', 'چه می‌خوانیم؟')
            ->assertJsonPath('data.children.0.id', $child->getKey());

        $this->assertStringStartsWith('http://', (string) $response->json('data.banners.0.image.sizes.large'));

        $this->get('/v1/categories/'.urlencode((string) $child->slug))
            ->assertJsonPath('data.trail', 'فناوری › موبایل')
            ->assertJsonPath('data.parent.id', $parent->getKey());

        $this->get('/v1/tags')->assertOk()->assertJsonPath('data.0.articles_count', 1);
        $this->get('/v1/tags/'.urlencode((string) $tag->slug))->assertOk()->assertJsonPath('data.name', 'اندروید');
        $this->get('/v1/tags/missing')->assertNotFound()->assertJsonPath('message', 'برچسب پیدا نشد.');
    }

    /**
     * Pages, categories, and tags come whole by default, a page at a time with page or per_page, and filter by text and parent.
     */
    public function test_pages_and_terms_page_and_filter_on_request(): void
    {
        $about = Page::query()->create(['title' => 'درباره ما', 'published_at' => now()->subDay()]);
        Page::query()->create(['title' => 'تماس', 'published_at' => now()->subDay()]);
        $team = Page::query()->create(['title' => 'تیم ما', 'parent_id' => $about->getKey(), 'published_at' => now()->subDay()]);

        $this->get('/v1/pages')->assertOk()->assertJsonCount(3, 'data')->assertJsonMissingPath('meta');
        $this->get('/v1/pages?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
        $this->get('/v1/pages?page=2&per_page=2')->assertOk()->assertJsonCount(1, 'data');
        $this->get('/v1/pages?page=1')->assertOk()->assertJsonPath('meta.per_page', 50);
        $this->get('/v1/pages?parent=0')->assertOk()->assertJsonCount(2, 'data');
        $this->get('/v1/pages?parent='.$about->getKey())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $team->getKey());
        $this->get('/v1/pages?q='.urlencode('تیم'))->assertOk()->assertJsonCount(1, 'data');
        $this->get('/v1/pages?per_page=101')->assertStatus(422)->assertJsonValidationErrors('per_page');

        $parent = Category::query()->create(['name' => 'فناوری']);
        $child = Category::query()->create(['name' => 'موبایل', 'parent_id' => $parent->getKey()]);
        Category::query()->create(['name' => 'ورزش']);

        $this->get('/v1/categories')->assertOk()->assertJsonCount(3, 'data')->assertJsonMissingPath('meta');
        $this->get('/v1/categories?per_page=1&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 3);
        $this->get('/v1/categories?parent=0')->assertOk()->assertJsonCount(2, 'data');
        $this->get('/v1/categories?parent='.$parent->getKey())->assertOk()->assertJsonPath('data.0.id', $child->getKey());
        $this->get('/v1/categories?q='.urlencode('ورز'))->assertOk()->assertJsonCount(1, 'data');

        Tag::query()->create(['name' => 'اندروید']);
        Tag::query()->create(['name' => 'آیفون']);

        $this->get('/v1/tags?q='.urlencode('اندر'))->assertOk()->assertJsonCount(1, 'data');
        $this->get('/v1/tags?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
    }

    /**
     * Media lists library files with their texts, filters by type, and never lists staff avatars.
     */
    public function test_media_lists_library_files_without_avatars(): void
    {
        $disk = Storage::fake('public');
        $disk->putFileAs('media', $this->picture('photo.png'), 'photo.png');
        $disk->put('media/guide.pdf', '%PDF-1.4');
        $disk->putFileAs('avatars', $this->picture('face.png'), 'face.png');
        Asset::query()->create(['path' => 'media/photo.png', 'title' => 'عکس دفتر', 'alt' => 'نمای دفتر', 'caption' => 'دفتر مرکزی', 'description' => 'توضیح']);

        $response = $this->get('/v1/media')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        $paths = array_column((array) $response->json('data'), 'path');
        $this->assertNotContains('avatars/face.png', $paths);

        $this->get('/v1/media?type=image')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.path', 'media/photo.png')
            ->assertJsonPath('data.0.title', 'عکس دفتر')
            ->assertJsonPath('data.0.alt', 'نمای دفتر')
            ->assertJsonPath('data.0.caption', 'دفتر مرکزی')
            ->assertJsonPath('data.0.image', true);

        $this->get('/v1/media?type=file')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.path', 'media/guide.pdf')
            ->assertJsonPath('data.0.sizes', null);

        $this->get('/v1/media/'.hash('sha1', 'media/photo.png'))
            ->assertOk()
            ->assertJsonPath('data.width', 1)
            ->assertJsonPath('data.height', 1);

        $this->get('/v1/media/'.hash('sha1', 'avatars/face.png'))
            ->assertNotFound()
            ->assertJsonPath('message', 'رسانه پیدا نشد.');
    }

    /**
     * A one-pixel PNG upload.
     */
    private function picture(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );
    }
}
