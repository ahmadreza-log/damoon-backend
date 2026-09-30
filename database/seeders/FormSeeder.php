<?php

namespace Database\Seeders;

use App\Models\Entry;
use App\Models\Form;
use Database\Factories\FormFactory;
use Illuminate\Database\Seeder;

/**
 * The sample forms in FormFactory::SAMPLES, each with a few messages in the inbox.
 *
 * A form whose slug already exists is kept with its messages, so running this again adds nothing new.
 *
 * Extending:
 * - Another sample form is one more entry in FormFactory::SAMPLES.
 */
class FormSeeder extends Seeder
{
    /** Messages each new sample form gets. */
    public const MESSAGES = 6;

    /**
     * Creates the sample forms and their messages.
     */
    public function run(): void
    {
        foreach (FormFactory::SAMPLES as $slug => [$title, $description, $fields]) {
            if (Form::query()->where('slug', $slug)->exists()) {
                continue;
            }

            $form = Form::factory()->create([
                'title' => $title,
                'slug' => $slug,
                'description' => $description,
                'fields' => $fields,
            ]);

            foreach (range(1, self::MESSAGES) as $index) {
                Entry::factory()->for($form)->create([
                    'status' => $index <= 2 ? Entry::NEW : fake()->randomElement([Entry::READ, Entry::READ, Entry::ARCHIVED]),
                    'created_at' => fake()->dateTimeBetween('-3 weeks', 'now'),
                ]);
            }
        }
    }
}
