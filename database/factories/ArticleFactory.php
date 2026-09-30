<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds fake articles for tests and local sample data.
 *
 * Each article gets a Persian title, an HTML body the model turns into Tiptap JSON,
 * two questions, and a publish date in the last three months. The author is a new
 * staff user unless one is given. scheduled() moves the publish date into the future.
 *
 * Extending:
 * - A new articles column needs a default here so Article::factory()->create() keeps working.
 *
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /** Persian titles a sample article is named after. */
    public const TITLES = [
        'راهنمای کامل شروع برنامه‌نویسی',
        'ده نکته برای افزایش سرعت سایت',
        'هوش مصنوعی چگونه کار ما را تغییر می‌دهد',
        'بهترین گوشی‌های میان‌رده امسال',
        'چطور یک عادت خوب بسازیم',
        'آشنایی با امنیت حساب‌های کاربری',
        'سفر به شمال در فصل پاییز',
        'اصول طراحی رابط کاربری ساده',
        'چرا نسخه‌ی پشتیبان مهم است',
        'معرفی ابزارهای کار تیمی',
        'تغذیه‌ی سالم برای روزهای شلوغ',
        'نکاتی برای راه‌اندازی کسب‌وکار اینترنتی',
    ];

    /**
     * The default column values for one fake article.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake('fa_IR')->randomElement(self::TITLES),
            'content' => self::body(),
            'author_id' => User::factory(),
            'published_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'questions' => [
                ['question' => fake('fa_IR')->realText(50).'؟', 'answer' => fake('fa_IR')->realText(150)],
                ['question' => fake('fa_IR')->realText(50).'؟', 'answer' => fake('fa_IR')->realText(150)],
            ],
            'gallery' => null,
            'commentable' => true,
        ];
    }

    /**
     * An article whose publish date is still to come, so the site does not show it yet.
     */
    public function scheduled(): static
    {
        return $this->state(fn (): array => ['published_at' => fake()->dateTimeBetween('+1 day', '+1 month')]);
    }

    /**
     * A Persian HTML body: a heading, a few paragraphs, and a short list.
     */
    public static function body(): string
    {
        $persian = fake('fa_IR');
        $paragraphs = array_map(fn (): string => '<p>'.$persian->realText(400).'</p>', range(1, $persian->numberBetween(2, 4)));
        $items = array_map(fn (): string => '<li>'.$persian->realText(60).'</li>', range(1, 3));

        return '<h2>'.$persian->realText(40).'</h2>'.implode('', $paragraphs).'<ul>'.implode('', $items).'</ul>';
    }
}
