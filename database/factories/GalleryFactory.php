<?php

namespace Database\Factories;

use App\Models\Gallery;
use App\Models\Kind;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds fake galleries for tests.
 *
 * Each gallery gets a Persian title, a kind, and a short description. It has no items, since
 * those must be real files in the media library.
 *
 * Extending:
 * - A new galleries column needs a default here so Gallery::factory()->create() keeps working.
 *
 * @extends Factory<Gallery>
 */
class GalleryFactory extends Factory
{
    /** Sample gallery titles. */
    public const TITLES = [
        'افتتاحیه دفتر مرکزی',
        'نمایشگاه الکامپ',
        'همایش سالانه',
        'کارگاه آموزشی',
        'گفتگو با مشتریان',
    ];

    /**
     * The default column values for one fake gallery.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $persian = fake('fa_IR');

        return [
            'title' => $persian->randomElement(self::TITLES),
            'kind' => $persian->randomElement(Kind::cases()),
            'description' => $persian->realText(120),
            'items' => null,
        ];
    }
}
