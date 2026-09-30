<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Page;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use Database\Factories\ArticleFactory;
use Database\Factories\TagFactory;
use Database\Seeders\ArticleSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CustomerSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the sample data seeders run by php artisan db:seed.
 *
 * Extending:
 * - A new seeder in DatabaseSeeder gets its rows checked here.
 */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An empty database gets install finished, testuser as owner, and rows in every section.
     *
     * A second run keeps one of each role, category, tag, page, and testuser.
     */
    public function test_database_seeder_fills_every_section(): void
    {
        $this->seed();

        $this->assertTrue(Setting::installed());
        $this->assertTrue(User::query()->where('username', 'testuser')->firstOrFail()->owner());
        $this->assertSame(UserSeeder::COUNT + 1, User::query()->count());
        $this->assertSame(['writer', 'support'], Role::query()->whereIn('name', ['writer', 'support'])->orderByDesc('name')->pluck('name')->all());
        $this->assertSame(CustomerSeeder::COUNT, Customer::query()->count());
        $this->assertSame(count(CategorySeeder::TREE, COUNT_RECURSIVE), Category::query()->count());
        $this->assertSame(count(TagFactory::NAMES), Tag::query()->count());
        $this->assertSame(count(ArticleFactory::TITLES), Article::query()->count());
        $this->assertSame(count(ArticleFactory::TITLES) - ArticleSeeder::SCHEDULED, Article::query()->published()->count());
        $this->assertSame(count(PageSeeder::TREE, COUNT_RECURSIVE), Page::query()->count());
        $this->assertGreaterThan(0, Comment::query()->where('status', Comment::APPROVED)->count());
        $this->assertSame(0, Article::query()->whereDoesntHave('categories')->count());

        $this->seed();

        $this->assertSame(1, User::query()->where('username', 'testuser')->count());
        $this->assertSame(count(CategorySeeder::TREE, COUNT_RECURSIVE), Category::query()->count());
        $this->assertSame(count(TagFactory::NAMES), Tag::query()->count());
        $this->assertSame(count(PageSeeder::TREE, COUNT_RECURSIVE), Page::query()->count());
        $this->assertSame(2, Role::query()->whereIn('name', ['writer', 'support'])->count());
    }
}
