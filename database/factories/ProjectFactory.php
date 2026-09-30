<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds fake projects for tests and local sample data.
 *
 * Each project gets a Persian title, the same kind of HTML description as an article, a
 * solar year, a few services, an industry, a duration, a city, and a client testimonial.
 * It has no logo or voice message, since those must be real files in the media library.
 *
 * Extending:
 * - A new projects column needs a default here so Project::factory()->create() keeps working.
 *
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /** Sample project titles. */
    public const TITLES = [
        'استقرار سامانه یکپارچه مالی',
        'راه‌اندازی مرکز داده',
        'طراحی پورتال سازمانی',
        'پیاده‌سازی شبکه اداری',
        'توسعه اپلیکیشن فروش',
        'مهاجرت زیرساخت به ابر',
    ];

    /** Services a sample project may list. */
    public const SERVICES = ['مشاوره', 'طراحی', 'پیاده‌سازی', 'پشتیبانی', 'آموزش', 'نگهداری'];

    /** Industries a sample project may belong to. */
    public const INDUSTRIES = ['بانکداری', 'پتروشیمی', 'خودروسازی', 'سلامت', 'آموزش', 'مخابرات'];

    /** Positions a sample client may hold. */
    public const POSITIONS = ['مدیرعامل', 'مدیر فناوری اطلاعات', 'مدیر پروژه', 'مدیر فنی'];

    /**
     * The default column values for one fake project.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $persian = fake('fa_IR');

        return [
            'title' => $persian->randomElement(self::TITLES),
            'content' => ArticleFactory::body(),
            'logo' => null,
            'year' => $persian->numberBetween(1395, 1404),
            'services' => $persian->randomElements(self::SERVICES, $persian->numberBetween(2, 4)),
            'industry' => $persian->randomElement(self::INDUSTRIES),
            'duration' => $persian->numberBetween(2, 18).' ماه',
            'location' => $persian->city(),
            'employer' => $persian->name(),
            'position' => $persian->randomElement(self::POSITIONS),
            'testimony' => $persian->realText(200),
            'voice' => null,
            'commentable' => true,
        ];
    }
}
