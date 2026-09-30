<?php

namespace Database\Factories;

use App\Models\Entry;
use App\Models\Form;
use App\Support\Fields;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds fake form messages for tests and local sample data.
 *
 * The answers follow the form's fields, with a made-up Persian value of the right kind for
 * each, and respect the fields' conditions. File fields are left empty, since a real file
 * would have to exist on the private disk. Messages are new unless a state says otherwise.
 *
 * Extending:
 * - A new field type needs an arm in sample so its answers look real.
 *
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    /**
     * The default column values for one fake message.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'customer_id' => null,
            'answers' => [],
            'status' => Entry::NEW,
            'ip' => fake()->ipv4(),
            'agent' => fake()->userAgent(),
            'source' => null,
        ];
    }

    /**
     * Fills the answers from the form when the state left them empty.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Entry $entry): void {
            if ($entry->answers === [] && $entry->form instanceof Form) {
                $entry->answers = self::answers($entry->form);
            }
        });
    }

    /**
     * A message someone has opened.
     */
    public function read(): static
    {
        return $this->state(['status' => Entry::READ]);
    }

    /**
     * A message put aside.
     */
    public function archived(): static
    {
        return $this->state(['status' => Entry::ARCHIVED]);
    }

    /**
     * Made-up answers for every field of the form that its conditions show.
     *
     * @return list<array{key: string, label: string, type: string, value: mixed}>
     */
    public static function answers(Form $form): array
    {
        $fields = $form->definition();
        $input = [];

        foreach (Fields::inputs($fields) as $field) {
            $input[(string) $field['key']] = self::sample($field);
        }

        $shown = Fields::shown($fields, $input);
        $answers = [];

        foreach (Fields::inputs($fields) as $field) {
            if ($shown[$field['key']] ?? true) {
                $answers[] = [
                    'key' => (string) $field['key'],
                    'label' => (string) $field['label'],
                    'type' => (string) $field['type'],
                    'value' => $input[$field['key']],
                ];
            }
        }

        return $answers;
    }

    /**
     * One made-up answer of the field's kind.
     *
     * @param  array<string, mixed>  $field
     */
    private static function sample(array $field): mixed
    {
        $persian = fake('fa_IR');
        $options = (array) $field['options'];

        return match ($field['type']) {
            'text' => $field['key'] === 'name' ? $persian->name() : $persian->realText(40),
            'textarea' => $persian->realText(220),
            'email' => fake()->safeEmail(),
            'phone' => '0912'.fake()->numerify('#######'),
            'number' => fake()->numberBetween((int) ($field['min'] ?? 0), (int) ($field['max'] ?? 20)),
            'url' => 'https://'.fake()->domainName(),
            'date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'select', 'radio' => $options === [] ? null : ($field['multiple'] ? fake()->randomElements($options, min(2, count($options))) : fake()->randomElement($options)),
            'checkboxes' => $options === [] ? [] : fake()->randomElements($options, fake()->numberBetween(1, count($options))),
            'checkbox' => true,
            default => null,
        };
    }
}
