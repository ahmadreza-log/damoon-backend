<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Page;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Support\Frontend;
use App\Support\Seo;
use Damoon\Schema\Schemas;
use Damoon\Schema\Vocabulary;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the damoon/schema package in the app and the public website addresses.
 *
 * Every article, page, brand, and project starts with its model's SCHEMAS filled with default
 * values and can change them in its own form; the site-wide schemas live on the general settings
 * page. Record addresses come from the site address and the patterns on that page.
 *
 * Extending:
 * - A new Schemable model gets a defaults assertion in the first test.
 * - A new schema type with its own renderer gets a render test here.
 */
class SchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Marks the site as installed with a public address and creates the owner.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->create([
            'title' => 'دامون',
            'description' => 'سامانه مدیریت محتوا',
            'installed_at' => now(),
            'url' => 'https://damoon.ir',
        ]);

        User::factory()->create(['username' => 'owner_user', 'email' => 'owner@example.com', 'phone' => '09120000001', 'firstname' => 'علی', 'lastname' => 'رضایی']);
    }

    /**
     * Each model starts with its own types, filled from the record, the site, and the address patterns.
     */
    public function test_records_start_with_their_model_defaults(): void
    {
        $owner = User::query()->firstOrFail();
        $article = Article::query()->create([
            'title' => 'نهال گردو',
            'slug' => 'walnut',
            'content' => '<p>کاشت نهال در پاییز</p>',
            'author_id' => $owner->getKey(),
            'published_at' => now(),
            'questions' => [['question' => 'کی بکاریم؟', 'answer' => 'پاییز']],
        ]);

        $this->assertNull($article->schemas);
        $this->assertSame(Schemas::defaults(Article::SCHEMAS), $article->schemata());

        [$document, $crumbs, $faq] = $article->structured();

        $this->assertSame('BlogPosting', $document['@type']);
        $this->assertSame('نهال گردو', $document['headline']);
        $this->assertSame('https://damoon.ir/articles/walnut', $document['mainEntityOfPage']);
        $this->assertSame('کاشت نهال در پاییز', $document['description']);
        $this->assertSame(['@type' => 'Person', 'name' => 'علی رضایی'], $document['author']);
        $this->assertSame('دامون', $document['publisher']['name']);

        $this->assertSame([
            ['خانه', 'https://damoon.ir'],
            ['نوشته‌ها', 'https://damoon.ir/articles'],
            ['نهال گردو', 'https://damoon.ir/articles/walnut'],
        ], array_map(fn (array $item): array => [$item['name'], $item['item']], $crumbs['itemListElement']));

        $this->assertSame('کی بکاریم؟', $faq['mainEntity'][0]['name']);
        $this->assertSame('پاییز', $faq['mainEntity'][0]['acceptedAnswer']['text']);

        $parent = Page::query()->create(['title' => 'خدمات', 'slug' => 'services', 'author_id' => $owner->getKey()]);
        $page = Page::query()->create(['title' => 'طراحی', 'slug' => 'design', 'parent_id' => $parent->getKey(), 'author_id' => $owner->getKey()]);

        [$webpage, $trail] = $page->structured();
        $this->assertSame('WebPage', $webpage['@type']);
        $this->assertSame(['@type' => 'WebSite', 'name' => 'دامون', 'url' => 'https://damoon.ir'], $webpage['isPartOf']);
        $this->assertSame(
            ['https://damoon.ir', 'https://damoon.ir/services', 'https://damoon.ir/design'],
            array_column($trail['itemListElement'], 'item'),
        );

        $this->assertSame(['Brand', 'BreadcrumbList'], array_column((new Brand)->schemata(), 'type'));
        $this->assertSame(['WebPage', 'BreadcrumbList'], array_column((new Project)->schemata(), 'type'));
        $this->assertSame(['Organization', 'WebSite'], array_column(Setting::current()->schemata(), 'type'));

        $bare = Page::query()->select(['id', 'title', 'slug'])->findOrFail($page->getKey());
        $this->assertNull($bare->markup);
        $this->assertCount(2, $bare->schemata());
    }

    /**
     * The edit form starts with the defaults; a record can switch them off and add its own JSON-LD.
     */
    public function test_a_record_overrides_its_schemas_in_its_form(): void
    {
        $owner = User::query()->firstOrFail();
        $article = Article::query()->create(['title' => 'نوشته', 'slug' => 'post', 'content' => '<p>متن</p>', 'author_id' => $owner->getKey(), 'published_at' => now()]);
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/articles/'.$article->getKey().'/edit')
            ->assertOk()
            ->assertSee('اسکیما (داده‌های ساختاریافته)')
            ->assertSee('مقاله (Article)')
            ->assertSee('{site_url}');

        $undo = Repeater::fake();

        $form = Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditArticle::class, ['record' => $article->getKey()]);

        $this->assertSame(Article::SCHEMAS, array_column(array_values($form->get('data.schemas')), 'type'));

        $form->fillForm(['schemas' => [
            ['type' => 'Article', 'active' => false, 'fields' => Vocabulary::defaults('Article')],
            ['type' => 'Custom', 'active' => true, 'fields' => ['json' => 'not json']],
        ]])
            ->call('save')
            ->assertHasFormErrors(['schemas.1.fields.json']);

        $form->fillForm(['schemas' => [
            ['type' => 'Article', 'active' => false, 'fields' => Vocabulary::defaults('Article')],
            ['type' => 'Custom', 'active' => true, 'fields' => ['json' => '{"@type": "HowTo", "name": "{title}", "url": "{url}"}']],
        ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $undo();

        $article->refresh();
        $this->assertSame(['Article', 'Custom'], array_column($article->schemas, 'type'));
        $this->assertSame([[
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => 'نوشته',
            'url' => 'https://damoon.ir/articles/post',
        ]], $article->structured());

        $article->update(['schemas' => []]);
        $this->assertSame([], $article->refresh()->structured());
    }

    /**
     * The general settings save the site address, the patterns, and the site schemas, and the API follows them.
     */
    public function test_the_settings_set_the_site_address_and_schemas(): void
    {
        $owner = User::query()->firstOrFail();
        $article = Article::query()->create(['title' => 'نهال', 'slug' => 'sapling', 'content' => '<p>متن</p>', 'author_id' => $owner->getKey(), 'published_at' => now()]);
        $token = $this->token($this->staff([Section::SETTINGS]));

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('نشانی سایت')
            ->assertSee('نشانی نوشته‌ها')
            ->assertSee('اسکیمای کل سایت')
            ->assertDontSee('مسیر راهنما (BreadcrumbList)');

        $undo = Repeater::fake();

        $form = Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)->test(SiteSettings::class);

        $form->fillForm(['routes' => ['articles' => 'blog']])
            ->call('save')
            ->assertHasFormErrors(['routes.articles']);

        $form->fillForm([
            'url' => 'https://www.damoon.ir/',
            'routes' => ['articles' => 'blog/{slug}', 'pages' => '/{slug}', 'brands' => '', 'projects' => '/work/{id}-{slug}'],
            'schemas' => [
                ['type' => 'Organization', 'active' => true, 'fields' => [...Vocabulary::defaults('Organization'), 'telephone' => '+982112345678']],
                ['type' => 'WebSite', 'active' => false, 'fields' => Vocabulary::defaults('WebSite')],
            ],
        ])
            ->call('save')
            ->assertHasNoFormErrors();

        $undo();

        $settings = Setting::current();
        $this->assertSame('https://www.damoon.ir', $settings->url);
        $this->assertSame(['articles' => '/blog/{slug}', 'projects' => '/work/{id}-{slug}'], $settings->routes);

        $this->getJson('/v1/schema')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.@type', 'Organization')
            ->assertJsonPath('data.0.url', 'https://www.damoon.ir')
            ->assertJsonPath('data.0.telephone', '+982112345678');

        $this->getJson('/v1/articles')->assertOk()->assertJsonPath('data.0.url', 'https://www.damoon.ir/blog/sapling');

        $seo = $this->getJson('/v1/articles/sapling')->assertOk()->json('data.seo');

        $this->assertSame('https://www.damoon.ir/blog/sapling', $seo['canonical']);
        $this->assertSame(['Organization', 'BlogPosting', 'BreadcrumbList'], array_column($seo['schema'], '@type'));
        $this->assertSame('https://www.damoon.ir/blog', $seo['schema'][2]['itemListElement'][1]['item']);

        $project = Project::query()->create(['title' => 'پروژه', 'slug' => 'tower', 'author_id' => $owner->getKey()]);
        $this->assertSame('https://www.damoon.ir/work/'.$project->getKey().'-tower', Frontend::link($project));
        $this->assertSame('https://www.damoon.ir/brands', Frontend::section(Brand::class));
        $this->assertNull(Frontend::section(Page::class));
    }

    /**
     * Without a site address the app address is used, and a stored schema_jsonld replaces the schemas.
     *
     * The panel saves seo_meta in Persian while the API runs in the app locale, so the API reads Seo::LOCALE rows.
     */
    public function test_fallbacks_and_the_stored_jsonld(): void
    {
        Setting::current()->update(['url' => null]);

        $owner = User::query()->firstOrFail();
        $article = Article::query()->create(['title' => 'نوشته', 'slug' => 'post', 'content' => '<p>متن</p>', 'author_id' => $owner->getKey(), 'published_at' => now()]);

        $this->assertSame(rtrim((string) config('app.url'), '/').'/articles/post', $article->getUrlForSEO());

        $article->seoMeta()->create(['locale' => Seo::LOCALE, 'title' => 'عنوان دستی', 'schema_jsonld' => ['@context' => 'https://schema.org', '@type' => 'Recipe', 'name' => 'دستی']]);

        $this->getJson('/v1/articles/post')
            ->assertOk()
            ->assertJsonPath('data.seo.schema.@type', 'Recipe')
            ->assertJsonPath('data.seo.title', fn (string $title): bool => str_contains($title, 'عنوان دستی'));
    }

    /**
     * Placeholders fill from the site and the record; unknown and inactive items are skipped.
     */
    public function test_rendering_skips_what_it_cannot_build(): void
    {
        $this->assertSame([], Schemas::render([
            ['type' => 'Unknown', 'active' => true, 'fields' => []],
            ['type' => 'Organization', 'active' => false, 'fields' => Vocabulary::defaults('Organization')],
            ['type' => 'BreadcrumbList', 'active' => true, 'fields' => Vocabulary::defaults('BreadcrumbList')],
            ['type' => 'Custom', 'active' => true, 'fields' => ['json' => '[]']],
            'broken',
        ]));

        $values = Schemas::values();
        $this->assertSame('دامون', $values['{site_name}']);
        $this->assertSame('https://damoon.ir', $values['{site_url}']);
        $this->assertSame((string) now()->year, $values['{year}']);
        $this->assertSame('', $values['{title}']);

        $documents = Schemas::render([['type' => 'Custom', 'active' => true, 'fields' => ['json' => '[{"@context": "https://schema.org", "@type": "Thing", "name": "{site_name} {year}"}, {"@type": "Thing"}]']]]);
        $this->assertCount(2, $documents);
        $this->assertSame('دامون '.now()->year, $documents[0]['name']);
        $this->assertArrayNotHasKey('Brand', Vocabulary::options(site: true));
        $this->assertArrayHasKey('Brand', Vocabulary::options());
    }

    /**
     * A staff account with the given sections.
     *
     * @param  list<string>  $sections
     */
    private function staff(array $sections): User
    {
        $user = User::factory()->create(['username' => 'staff_user', 'email' => 'staff@example.com', 'phone' => '09120000004']);
        $user->grant($sections);

        return $user;
    }

    /**
     * A panel session token for the given user.
     */
    private function token(User $user): string
    {
        return app(AccessTokens::class)->issue($user, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);
    }
}
