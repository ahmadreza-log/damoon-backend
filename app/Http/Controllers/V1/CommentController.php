<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CommentResource;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Comments (دیدگاه‌ها) on published articles and pages, version 1.
 *
 * Routes are /v1/articles/{slug}/comments and /v1/pages/{slug}/comments; each route passes its
 * type (articles or pages) as a route default. Reading is public
 * and sends only approved comments. Anyone may send one while the article or page allows
 * comments; a customer Bearer token is optional and fills the name and email from the
 * account. New comments wait for approval in the panel before the site shows them.
 *
 * Extending:
 * - Another model that takes comments goes in Comment::SUBJECTS; the routes read that list.
 */
class CommentController extends Controller
{
    /** Top-level comments on one page of the list. */
    public const PAGE = 20;

    /**
     * Approved comments, oldest first, a page of top-level comments at a time, each with its approved replies.
     *
     * Twenty top-level comments per page; pass page for the next ones. Replies are one level deep and
     * come inside their comment in replies. staff is true for an answer from the site's team.
     * Email, IP, and browser are never sent. An unknown or unpublished slug gets 404.
     *
     * @return AnonymousResourceCollection<LengthAwarePaginator<int, CommentResource>>
     */
    public function index(Request $request, string $slug): AnonymousResourceCollection
    {
        $comments = $this->subject($request, $slug)
            ->comments()
            ->approved()
            ->whereNull('parent_id')
            ->with(['replies' => fn ($query) => $query->approved()->oldest()->oldest('id')])
            ->oldest()
            ->oldest('id')
            ->paginate(self::PAGE);

        return CommentResource::collection($comments);
    }

    /**
     * Sends a comment, or an answer to an approved comment with parent_id. It waits for approval.
     *
     * No token is needed. With a customer Bearer token, name and email may be left out and are
     * taken from the account; without one, name is required. The comment is saved as pending
     * and shows in the list only after staff approve it in the panel. An article or page whose
     * comments are turned off gets 403, an unknown or unpublished slug gets 404, and more than
     * five comments a minute from one IP get 429.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $subject = $this->subject($request, $slug);

        abort_unless((bool) $subject->commentable, 403, 'ارسال دیدگاه برای اینجا بسته است.');

        $customer = Auth::guard('customer')->user();
        $customer = $customer instanceof Customer ? $customer : null;

        $data = $request->validate([
            'name' => [$customer ? 'nullable' : 'required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'body' => ['required', 'string', 'max:'.Comment::LIMIT],
            'parent_id' => ['nullable', 'integer'],
        ], [
            'required' => 'فیلد :attribute الزامی است.',
            'email' => ':attribute معتبر نیست.',
            'max' => ':attribute بیش از حد طولانی است.',
            'integer' => ':attribute معتبر نیست.',
            'string' => ':attribute معتبر نیست.',
        ], [
            'name' => 'نام',
            'email' => 'ایمیل',
            'body' => 'متن دیدگاه',
            'parent_id' => 'دیدگاهی که به آن پاسخ می‌دهید',
        ]);

        if (isset($data['parent_id']) && ! $subject->comments()->approved()->whereKey($data['parent_id'])->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'دیدگاهی که به آن پاسخ می‌دهید پیدا نشد.']);
        }

        $comment = $subject->comments()->create([
            'parent_id' => $data['parent_id'] ?? null,
            'customer_id' => $customer?->getKey(),
            'name' => $customer && blank($data['name'] ?? null) ? ($customer->name() ?: $customer->username) : (string) $data['name'],
            'email' => $data['email'] ?? $customer?->email,
            'body' => (string) $data['body'],
            'status' => Comment::PENDING,
            'ip' => $request->ip(),
            'agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        return response()->json([
            'message' => 'دیدگاه شما ثبت شد و پس از تأیید نمایش داده می‌شود.',
            'data' => new CommentResource($comment->refresh()),
        ], 201);
    }

    /**
     * The published article or page a route points at; an unknown or unpublished slug gets 404.
     */
    private function subject(Request $request, string $slug): Article|Page
    {
        $type = (string) $request->route('type');
        $model = Comment::SUBJECTS[$type];
        $subject = $model::query()->published()->where('slug', $slug)->first();

        abort_if($subject === null, 404, $type === 'pages' ? 'برگه پیدا نشد.' : 'نوشته پیدا نشد.');

        return $subject;
    }
}
