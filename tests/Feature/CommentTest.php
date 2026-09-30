<?php

namespace Tests\Feature;

use App\Auth\AccessTokens;
use App\Auth\Section;
use App\Filament\Resources\Comments\Pages\EditComment;
use App\Filament\Resources\Comments\Pages\ListComments;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers comments (دیدگاه‌ها): sending and reading them through the v1 API, and moderating them in the panel.
 *
 * Extending:
 * - A new moderation action or API rule gets a test here.
 */
class CommentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Marks the site as installed so EnsureInstalled lets panel requests through.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->create([
            'title' => 'دامون',
            'description' => 'سامانه مدیریت محتوا',
            'installed_at' => now(),
        ]);
    }

    /**
     * A guest comment waits for approval, then shows with its replies; email and IP never leave the server.
     */
    public function test_guests_send_comments_that_show_after_approval(): void
    {
        $article = $this->article();
        $path = '/v1/articles/'.urlencode((string) $article->slug).'/comments';

        $this->postJson($path, ['name' => 'مریم', 'email' => 'maryam@example.com', 'body' => 'نوشتهٔ خوبی بود.'])
            ->assertCreated()
            ->assertJsonPath('message', 'دیدگاه شما ثبت شد و پس از تأیید نمایش داده می‌شود.')
            ->assertJsonPath('data.status', Comment::PENDING)
            ->assertJsonMissingPath('data.email');

        $comment = Comment::query()->sole();
        $this->assertSame('maryam@example.com', $comment->email);
        $this->assertNotNull($comment->ip);
        $this->assertTrue($comment->subject->is($article));

        $this->getJson($path)->assertOk()->assertJsonCount(0, 'data');

        $comment->mark(Comment::APPROVED);
        $this->postJson($path, ['name' => 'علی', 'body' => 'موافقم.', 'parent_id' => $comment->getKey()])->assertCreated();
        $reply = Comment::query()->latest('id')->first();
        $reply->mark(Comment::APPROVED);

        $this->postJson($path, ['name' => 'سارا', 'body' => 'من هم.', 'parent_id' => $reply->getKey()])->assertCreated();
        $deeper = Comment::query()->latest('id')->first();
        $this->assertSame($comment->getKey(), $deeper->parent_id);

        $this->getJson($path)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'مریم')
            ->assertJsonPath('data.0.body', 'نوشتهٔ خوبی بود.')
            ->assertJsonPath('data.0.staff', false)
            ->assertJsonCount(1, 'data.0.replies')
            ->assertJsonPath('data.0.replies.0.name', 'علی')
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.ip');

        $this->getJson('/v1/articles/'.urlencode((string) $article->slug))
            ->assertJsonPath('data.comments_count', 2);
    }

    /**
     * Closed, unpublished, and unknown subjects refuse comments, and bad input gets Persian errors.
     */
    public function test_comments_are_refused_when_not_allowed(): void
    {
        $closed = Page::query()->create(['title' => 'تماس', 'published_at' => now()->subDay(), 'commentable' => false]);
        $draft = $this->article(['title' => 'پیش‌نویس', 'published_at' => now()->addWeek()]);
        $open = $this->article();
        $other = $this->article(['title' => 'دیگری']);
        $foreign = $other->comments()->create(['name' => 'رضا', 'body' => 'سلام', 'status' => Comment::APPROVED]);
        $pending = $open->comments()->create(['name' => 'نگار', 'body' => 'سلام', 'status' => Comment::PENDING]);
        $path = '/v1/articles/'.urlencode((string) $open->slug).'/comments';

        $this->postJson('/v1/pages/'.urlencode((string) $closed->slug).'/comments', ['name' => 'مریم', 'body' => 'سلام'])
            ->assertForbidden()
            ->assertJsonPath('message', 'ارسال دیدگاه برای اینجا بسته است.');
        $this->postJson('/v1/articles/'.urlencode((string) $draft->slug).'/comments', ['name' => 'مریم', 'body' => 'سلام'])
            ->assertNotFound()
            ->assertJsonPath('message', 'نوشته پیدا نشد.');
        $this->getJson('/v1/pages/missing/comments')->assertNotFound()->assertJsonPath('message', 'برگه پیدا نشد.');
        $this->getJson('/v1/products/'.urlencode((string) $open->slug).'/comments')->assertNotFound();
        $this->postJson('/v1/products/'.urlencode((string) $open->slug).'/comments', ['name' => 'مریم', 'body' => 'سلام'])->assertNotFound();

        $this->postJson($path, ['email' => 'bad', 'body' => str_repeat('ا', Comment::LIMIT + 1)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'فیلد نام الزامی است.')
            ->assertJsonPath('errors.email.0', 'ایمیل معتبر نیست.')
            ->assertJsonPath('errors.body.0', 'متن دیدگاه بیش از حد طولانی است.');

        $this->postJson($path, ['name' => 'مریم', 'body' => 'پاسخ', 'parent_id' => $foreign->getKey()])
            ->assertJsonValidationErrors('parent_id');
        $this->postJson($path, ['name' => 'مریم', 'body' => 'پاسخ', 'parent_id' => $pending->getKey()])
            ->assertJsonValidationErrors('parent_id');

        $this->assertSame(2, Comment::query()->count());

        $this->postJson($path, ['name' => 'مریم', 'body' => 'ششمین تلاش'])->assertTooManyRequests();
        $this->getJson($path)->assertOk();
    }

    /**
     * A customer token fills the name and email from the account.
     */
    public function test_customers_comment_with_their_account(): void
    {
        $article = $this->article();
        $customer = Customer::factory()->create(['firstname' => 'زهرا', 'lastname' => 'کریمی', 'email' => 'zahra@example.com']);
        $token = app(AccessTokens::class)->issue($customer, AccessTokens::ABILITY_API, AccessTokens::ABILITY_API, 60);

        $this->withToken($token)
            ->postJson('/v1/articles/'.urlencode((string) $article->slug).'/comments', ['body' => 'ممنون از نوشته.'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'زهرا کریمی');

        $comment = Comment::query()->sole();
        $this->assertSame($customer->getKey(), $comment->customer_id);
        $this->assertSame('zahra@example.com', $comment->email);
    }

    /**
     * Staff with the comments section approve, answer, mark spam, and edit; others get 403.
     */
    public function test_staff_moderate_comments_in_the_panel(): void
    {
        $owner = $this->owner();
        $article = $this->article();
        $comment = $article->comments()->create(['name' => 'مریم', 'email' => 'maryam@example.com', 'body' => 'یک سوال دارم.', 'status' => Comment::PENDING]);
        $junk = $article->comments()->create(['name' => 'تبلیغ', 'body' => 'خرید ارزان', 'status' => Comment::PENDING]);
        $token = $this->token($owner);

        $this->withCookie((string) config('sanctum.panel_cookie'), $token)
            ->get('/admin/comments')
            ->assertOk()
            ->assertSee('دیدگاه‌ها')
            ->assertSee('در انتظار تأیید')
            ->assertSee('یک سوال دارم.')
            ->assertSee('راهنمای دامون');

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(ListComments::class)
            ->assertCanSeeTableRecords([$comment, $junk])
            ->callTableAction('spam', $junk)
            ->callTableAction('reply', $comment, data: ['body' => 'پاسخ شما این است.'])
            ->assertHasNoTableActionErrors()
            ->set('activeTab', Comment::SPAM)
            ->assertCanSeeTableRecords([$junk])
            ->assertCanNotSeeTableRecords([$comment]);

        $this->assertSame(Comment::SPAM, $junk->refresh()->status);
        $this->assertSame(Comment::APPROVED, $comment->refresh()->status);
        $answer = $comment->replies()->sole();
        $this->assertSame(Comment::APPROVED, $answer->status);
        $this->assertSame($owner->getKey(), $answer->user_id);
        $this->assertTrue($answer->subject->is($article));

        $this->getJson('/v1/articles/'.urlencode((string) $article->slug).'/comments')
            ->assertJsonPath('data.0.replies.0.body', 'پاسخ شما این است.')
            ->assertJsonPath('data.0.replies.0.staff', true);

        Livewire::withCookie((string) config('sanctum.panel_cookie'), $token)
            ->test(EditComment::class, ['record' => $comment->getKey()])
            ->fillForm(['body' => 'یک سوال مهم دارم.', 'status' => Comment::PENDING])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('یک سوال مهم دارم.', $comment->refresh()->body);
        $this->assertSame(Comment::PENDING, $comment->status);

        $member = User::factory()->create(['username' => 'member_user', 'email' => 'member@example.com', 'phone' => '09120000002']);
        $member->grant([Section::ARTICLES]);

        $this->withCookie((string) config('sanctum.panel_cookie'), $this->token($member))
            ->get('/admin/comments')
            ->assertForbidden();
    }

    /**
     * Deleting a comment takes its replies, and deleting an article takes its comments.
     */
    public function test_comments_leave_with_their_parent_and_subject(): void
    {
        $article = $this->article();
        $comment = $article->comments()->create(['name' => 'مریم', 'body' => 'سلام', 'status' => Comment::APPROVED]);
        $article->comments()->create(['name' => 'علی', 'body' => 'سلام', 'status' => Comment::APPROVED, 'parent_id' => $comment->getKey()]);
        $article->comments()->create(['name' => 'سارا', 'body' => 'درود', 'status' => Comment::APPROVED]);

        $comment->delete();
        $this->assertSame(1, Comment::query()->count());

        $article->delete();
        $this->assertSame(0, Comment::query()->count());
    }

    /**
     * A published article that allows comments.
     *
     * @param  array<string, mixed>  $values
     */
    private function article(array $values = []): Article
    {
        return Article::query()->create($values + [
            'title' => 'راهنمای دامون',
            'published_at' => now()->subHour(),
        ]);
    }

    /**
     * The first user, who becomes the owner.
     */
    private function owner(): User
    {
        return User::factory()->create([
            'username' => 'owner_user',
            'email' => 'owner@example.com',
            'phone' => '09120000001',
        ]);
    }

    /**
     * A panel token for the given user.
     */
    private function token(User $user): string
    {
        return app(AccessTokens::class)->issue($user, AccessTokens::ABILITY_PANEL, AccessTokens::ABILITY_PANEL, 60);
    }
}
