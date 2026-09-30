<?php

namespace Database\Factories;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds fake brands for tests and local sample data.
 *
 * Each brand gets a Persian title, an English title, the same kind of HTML description as
 * an article, three features, and website, LinkedIn, and download links. It has no logo or
 * catalog, since those must be real files in the media library.
 *
 * Extending:
 * - A new brands column needs a default here so Brand::factory()->create() keeps working.
 *
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    /** Sample brands: Persian title => English title. */
    public const NAMES = [
        'داده‌پردازان پارس' => 'Pars Data',
        'نوآوران آسیا' => 'Asia Innovators',
        'سپهر افزار' => 'Sepehr Soft',
        'رایان گستر' => 'Rayan Gostar',
        'آرتا صنعت' => 'Arta Industry',
        'نیک‌ارتباط' => 'Nik Ertebat',
    ];

    /**
     * The default column values for one fake brand.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $persian = fake('fa_IR');
        $title = $persian->randomElement(array_keys(self::NAMES));
        $domain = strtolower(str_replace(' ', '', self::NAMES[$title])).'.com';

        return [
            'title' => $title,
            'english' => self::NAMES[$title],
            'content' => ArticleFactory::body(),
            'features' => array_map(fn (): array => [
                'title' => $persian->realText(30),
                'description' => $persian->realText(120),
            ], range(1, 3)),
            'website' => 'https://'.$domain,
            'linkedin' => 'https://www.linkedin.com/company/'.strtok($domain, '.'),
            'download' => 'https://'.$domain.'/download',
            'catalog' => null,
            'logo' => null,
        ];
    }
}
