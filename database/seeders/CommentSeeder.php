<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Brand;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A few comments on every published article and page, and every brand and project, that takes them.
 *
 * Most are approved, some wait for approval, and a few are spam, so every tab on the
 * comments page has rows. Some come from customers, and about a third of the approved
 * ones get a staff answer through Comment::answer. Anything that already has comments is
 * skipped, so running this again only fills new content.
 *
 * Extending:
 * - Run the content, customer, and user seeders first; this reads what they made.
 */
class CommentSeeder extends Seeder
{
    /**
     * Creates the sample comments and answers.
     */
    public function run(): void
    {
        $customers = Customer::query()->get();
        $staff = User::query()->get();

        $subjects = collect([
            ...Article::query()->published()->where('commentable', true)->get(),
            ...Page::query()->published()->where('commentable', true)->get(),
            ...Brand::query()->where('commentable', true)->get(),
            ...Project::query()->where('commentable', true)->get(),
        ]);

        foreach ($subjects as $subject) {
            if ($subject->comments()->exists()) {
                continue;
            }

            foreach (range(1, fake()->numberBetween(1, 4)) as $ignored) {
                $comment = $this->comment($subject, $customers->isNotEmpty() && fake()->boolean(30) ? $customers->random() : null);

                if ($comment->status === Comment::APPROVED && $staff->isNotEmpty() && fake()->boolean(35)) {
                    $comment->answer($staff->random(), fake('fa_IR')->realText(160));
                }
            }
        }
    }

    /**
     * One top-level comment on the subject, from a customer when one is given and a guest otherwise.
     */
    private function comment(Article|Page|Brand|Project $subject, ?Customer $customer): Comment
    {
        $status = fake()->randomElement([...array_fill(0, 6, Comment::APPROVED), ...array_fill(0, 3, Comment::PENDING), Comment::SPAM]);
        $published = $subject instanceof Article || $subject instanceof Page ? $subject->published_at : null;
        $from = $published !== null && $published->isPast() ? $published : now()->subWeek();

        return Comment::factory()->for($subject, 'subject')->create([
            'customer_id' => $customer?->getKey(),
            'name' => $customer !== null ? ($customer->name() ?: $customer->username) : fake('fa_IR')->name(),
            'email' => $customer !== null ? $customer->email : fake()->safeEmail(),
            'status' => $status,
            'created_at' => fake()->dateTimeBetween($from, 'now'),
        ]);
    }
}
