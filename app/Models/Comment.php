<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A visitor comment (دیدگاه) on an article or a page.
 *
 * subject is the article or page. A comment comes from a guest with a name and email,
 * a signed-in customer, or a staff member answering in the panel. New comments from the
 * site wait as pending until staff approve them; only approved ones reach the site.
 * Replies stay one level deep: a reply to a reply is stored under the top-level comment,
 * so the site draws a comment and its answers without deeper nesting.
 *
 * Extending:
 * - Another model that takes comments needs a comments() relation, a commentable column, a name in kinds, and a Subject case.
 * - A new status needs a constant, a label in statuses, and a colour in CommentResource.
 */
#[Fillable([
    'subject_type',
    'subject_id',
    'parent_id',
    'customer_id',
    'user_id',
    'name',
    'email',
    'body',
    'status',
    'ip',
    'agent',
])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    /** Waiting for staff to approve it; hidden from the site. */
    public const PENDING = 'pending';

    /** Shown on the site. */
    public const APPROVED = 'approved';

    /** Marked as unwanted; hidden from the site. */
    public const SPAM = 'spam';

    /** The longest comment body, in characters. */
    public const LIMIT = 3000;

    /**
     * Persian status labels for the panel.
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::PENDING => 'در انتظار تأیید',
            self::APPROVED => 'تأییدشده',
            self::SPAM => 'هرزنامه',
        ];
    }

    /**
     * Persian names of the models that take comments.
     *
     * @return array<class-string<Model>, string>
     */
    public static function kinds(): array
    {
        return [
            Article::class => 'نوشته',
            Page::class => 'برگه',
        ];
    }

    /**
     * Keeps replies one level deep.
     *
     * Eloquent owns this method name.
     */
    protected static function booted(): void
    {
        static::saving(function (Comment $comment): void {
            $comment->flatten();
        });
    }

    /**
     * The article or page this comment is on.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The top-level comment this one answers.
     *
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Answers to this comment.
     *
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /**
     * The signed-in customer who wrote it, if any.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The staff member who answered from the panel, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Comments the site may show.
     *
     * @param  Builder<Comment>  $query
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', self::APPROVED);
    }

    /**
     * Sets the status and saves.
     */
    public function mark(string $status): void
    {
        $this->forceFill(['status' => $status])->save();
    }

    /**
     * Publishes a staff answer under this comment and approves this comment.
     */
    public function answer(User $user, string $body): Comment
    {
        if ($this->status !== self::APPROVED) {
            $this->mark(self::APPROVED);
        }

        return Comment::query()->create([
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'parent_id' => $this->getKey(),
            'user_id' => $user->getKey(),
            'name' => $user->getFilamentName(),
            'email' => $user->email,
            'body' => $body,
            'status' => self::APPROVED,
        ]);
    }

    /**
     * The Persian name of what this comment is on, such as نوشته.
     */
    public function kind(): string
    {
        return self::kinds()[$this->subject_type] ?? '';
    }

    /**
     * Moves a reply to a reply under the top-level comment.
     */
    private function flatten(): void
    {
        if ($this->parent_id === null) {
            return;
        }

        $root = Comment::query()->whereKey($this->parent_id)->value('parent_id');

        if ($root !== null) {
            $this->parent_id = $root;
        }
    }
}
