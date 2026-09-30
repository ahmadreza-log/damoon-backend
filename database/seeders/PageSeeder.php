<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The usual site pages, with تیم ما and تاریخچه under درباره ما.
 *
 * Pages are ordered by their place in TREE. A page whose title already exists is
 * kept, so running this again adds nothing new.
 *
 * Extending:
 * - Another page is one more entry in TREE, with its children as the value.
 */
class PageSeeder extends Seeder
{
    /**
     * Top-level page titles and the titles of the pages under them.
     *
     * @var array<string, array<int, string>>
     */
    public const TREE = [
        'درباره ما' => ['تیم ما', 'تاریخچه'],
        'تماس با ما' => [],
        'سوالات متداول' => [],
        'قوانین و مقررات' => [],
        'حریم خصوصی' => [],
    ];

    /**
     * Creates the sample pages.
     */
    public function run(): void
    {
        $authors = User::query()->get();
        $position = 0;

        foreach (self::TREE as $title => $children) {
            $parent = $this->page($title, null, $position++, $authors->random()->getKey());

            foreach ($children as $order => $child) {
                $this->page($child, $parent->getKey(), $order, $authors->random()->getKey());
            }
        }
    }

    /**
     * The page with this title, created when it does not exist yet.
     */
    private function page(string $title, ?int $parent, int $position, int $author): Page
    {
        return Page::query()->firstWhere('title', $title) ?? Page::factory()->create([
            'title' => $title,
            'parent_id' => $parent,
            'position' => $position,
            'author_id' => $author,
            'commentable' => $title !== 'قوانین و مقررات' && $title !== 'حریم خصوصی',
        ]);
    }
}
