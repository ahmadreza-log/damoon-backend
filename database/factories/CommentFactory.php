<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds fake visitor comments for tests and local sample data.
 *
 * A comment is from a guest with a Persian name, waits for approval, and sits on a
 * new article unless for($subject, 'subject') picks one. approved() and spam() set
 * the other statuses.
 *
 * Extending:
 * - A new comments column needs a default here so Comment::factory()->create() keeps working.
 *
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * The default column values for one fake comment.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => (new Article)->getMorphClass(),
            'subject_id' => Article::factory(),
            'parent_id' => null,
            'customer_id' => null,
            'user_id' => null,
            'name' => fake('fa_IR')->name(),
            'email' => fake()->safeEmail(),
            'body' => fake('fa_IR')->realText(fake()->numberBetween(80, 300)),
            'status' => Comment::PENDING,
            'ip' => fake()->ipv4(),
            'agent' => fake()->userAgent(),
        ];
    }

    /**
     * A comment the site shows.
     */
    public function approved(): static
    {
        return $this->state(['status' => Comment::APPROVED]);
    }

    /**
     * A comment marked as unwanted.
     */
    public function spam(): static
    {
        return $this->state(['status' => Comment::SPAM]);
    }
}
