<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Seeder;

/**
 * One sample article for each title in ArticleFactory::TITLES.
 *
 * Each one sits in one or two categories, carries a few tags, and lists two related
 * articles. The last two are scheduled for later, so the site does not show them yet,
 * and one has comments turned off. Authors are staff with the نویسنده role.
 *
 * Extending:
 * - Run CategorySeeder, TagSeeder, and UserSeeder first; this reads what they made.
 */
class ArticleSeeder extends Seeder
{
    /** Articles at the end of the list that are scheduled for later. */
    public const SCHEDULED = 2;

    /**
     * Creates the sample articles and links them to categories, tags, and each other.
     */
    public function run(): void
    {
        $authors = User::role('writer')->get();
        $authors = $authors->isNotEmpty() ? $authors : User::query()->get();
        $categories = Category::query()->get();
        $tags = Tag::query()->get();
        $titles = ArticleFactory::TITLES;
        $last = count($titles) - self::SCHEDULED;

        $articles = collect($titles)->map(function (string $title, int $index) use ($authors, $last): Article {
            $factory = Article::factory()->state([
                'title' => $title,
                'author_id' => $authors->random()->getKey(),
                'commentable' => $index !== 1,
            ]);

            return ($index >= $last ? $factory->scheduled() : $factory)->create();
        });

        foreach ($articles as $article) {
            if ($categories->isNotEmpty()) {
                $article->categories()->attach($categories->random(min($categories->count(), fake()->numberBetween(1, 2)))->modelKeys());
            }

            if ($tags->isNotEmpty()) {
                $article->tags()->attach($tags->random(min($tags->count(), fake()->numberBetween(1, 3)))->modelKeys());
            }

            $article->related()->attach($articles->where('id', '!=', $article->getKey())->random(2)->pluck('id')->all());
        }
    }
}
