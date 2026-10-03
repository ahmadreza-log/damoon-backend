<?php

namespace App\Filament\Pages;

use App\Auth\Section;
use App\Filament\Resources\ApiKeys\ApiKeyResource;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Brands\BrandResource;
use App\Filament\Resources\Comments\CommentResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Entries\EntryResource;
use App\Filament\Resources\Forms\FormResource;
use App\Filament\Resources\Galleries\GalleryResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Degree;
use App\Models\Gender;
use App\Models\Page as PageModel;
use App\Models\User;
use App\Support\Shamsi;
use App\Support\Sizes;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

/**
 * The signed-in user's own profile at /admin/profile, opened from the user menu.
 *
 * It shows the avatar, name, roles, and job, the account and personnel details, the panel
 * sections the user may open (each linked to its page), a count of what they wrote, and their
 * latest articles. Every signed-in user may open it, whatever their sections.
 *
 * Extending:
 * - A new section in App\Auth\Section gets a link in links.
 * - Editing lives on App\Filament\Auth\EditProfile; the header action opens it.
 * - Filament owns canAccess, getHeaderActions, getViewData, and the view and slug properties.
 */
class Profile extends Page
{
    /** The page heading and browser tab title. */
    protected static ?string $title = 'پروفایل من';

    /** Opened from the user menu, not the side menu. */
    protected static bool $shouldRegisterNavigation = false;

    /** /admin/profile; editing is /admin/profile/edit. */
    protected static ?string $slug = 'profile';

    protected string $view = 'filament.pages.profile';

    /** How many of the user's latest articles the page lists. */
    private const LATEST = 5;

    /**
     * Any signed-in panel user may see their own profile.
     *
     * Filament owns this method name.
     */
    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    /**
     * ویرایش پروفایل, next to the heading.
     *
     * Filament owns this method name.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('ویرایش پروفایل')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->url(Filament::getProfileUrl()),
        ];
    }

    /**
     * Everything the profile view draws.
     *
     * Filament owns this method name.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = $this->user();

        return [
            'name' => $user->getFilamentName(),
            'avatar' => $this->avatar($user),
            'ranks' => $user->ranks(),
            'job' => $user->job,
            'owner' => $user->owner(),
            'joined' => $user->created_at === null ? null : $this->persian(Jalalian::fromCarbon($user->created_at->copy()->timezone(Shamsi::ZONE))->format('j F Y')),
            'seen' => $user->last_login?->locale('fa')->diffForHumans(),
            'account' => $this->account($user),
            'personal' => $this->personal($user),
            'links' => $this->links($user),
            'stats' => $this->stats($user),
            'latest' => $this->latest($user),
            'mine' => ArticleResource::canViewAny() ? ArticleResource::getUrl('index', ['filters' => ['author_id' => ['value' => $user->getKey()]]]) : null,
            'write' => ArticleResource::canCreate() ? ArticleResource::getUrl('create') : null,
        ];
    }

    /**
     * The signed-in user. canAccess guarantees one.
     */
    private function user(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * The medium avatar size for the large picture, or the panel avatar when there is none.
     */
    private function avatar(User $user): string
    {
        if (is_string($user->avatar) && $user->avatar !== '') {
            $disk = Storage::disk('public');

            if ($disk instanceof FilesystemAdapter && $disk->exists($user->avatar)) {
                return $disk->url(Sizes::pick($user->avatar, 'medium'));
            }
        }

        return (string) $user->getFilamentAvatarUrl();
    }

    /**
     * Username, email, and phone, with the left-to-right flag for the view.
     *
     * @return list<array{label: string, value: string, icon: Heroicon, ltr: bool}>
     */
    private function account(User $user): array
    {
        return [
            ['label' => 'نام کاربری', 'value' => (string) $user->username, 'icon' => Heroicon::OutlinedAtSymbol, 'ltr' => true],
            ['label' => 'ایمیل', 'value' => (string) $user->email, 'icon' => Heroicon::OutlinedEnvelope, 'ltr' => true],
            ['label' => 'شماره تلفن', 'value' => filled($user->phone) ? (string) $user->phone : '—', 'icon' => Heroicon::OutlinedPhone, 'ltr' => filled($user->phone)],
        ];
    }

    /**
     * Personnel code, national code, education, and gender, with a dash for anything empty.
     *
     * @return list<array{label: string, value: string}>
     */
    private function personal(User $user): array
    {
        $degree = Degree::options()[$user->degree] ?? null;
        $gender = Gender::options()[$user->gender] ?? null;

        return [
            ['label' => 'کد پرسنلی', 'value' => filled($user->personnel) ? $this->persian((string) $user->personnel) : '—'],
            ['label' => 'کد ملی', 'value' => filled($user->national) ? $this->persian((string) $user->national) : '—'],
            ['label' => 'مدرک تحصیلی', 'value' => $degree ?? '—'],
            ['label' => 'رشته تحصیلی', 'value' => filled($user->major) ? (string) $user->major : '—'],
            ['label' => 'جنسیت', 'value' => $gender ?? '—'],
        ];
    }

    /**
     * The sections the user may open, each with the address of its page.
     *
     * @return list<array{label: string, url: string}>
     */
    private function links(User $user): array
    {
        $pages = [
            Section::HOME => fn (): string => Dashboard::getUrl(),
            Section::USERS => fn (): string => UserResource::getUrl('index'),
            Section::CUSTOMERS => fn (): string => CustomerResource::getUrl('index'),
            Section::ROLES => fn (): string => RoleResource::getUrl('index'),
            Section::ARTICLES => fn (): string => ArticleResource::getUrl('index'),
            Section::PAGES => fn (): string => PageResource::getUrl('index'),
            Section::BRANDS => fn (): string => BrandResource::getUrl('index'),
            Section::PROJECTS => fn (): string => ProjectResource::getUrl('index'),
            Section::GALLERIES => fn (): string => GalleryResource::getUrl('index'),
            Section::MEDIA => fn (): string => Media::getUrl(),
            Section::COMMENTS => fn (): string => CommentResource::getUrl('index'),
            Section::FORMS => fn (): string => FormResource::getUrl('index'),
            Section::INBOX => fn (): string => EntryResource::getUrl('index'),
            Section::SETTINGS => fn (): string => SiteSettings::getUrl(),
            Section::SOCIALS => fn (): string => SocialSettings::getUrl(),
            Section::API => fn (): string => ApiKeyResource::getUrl('index'),
        ];

        $labels = Section::options();
        $links = [];

        foreach ($user->sections() as $key) {
            if (isset($pages[$key], $labels[$key])) {
                $links[] = ['label' => $labels[$key], 'url' => $pages[$key]()];
            }
        }

        return $links;
    }

    /**
     * How many articles and pages the user wrote and how many comment replies they sent.
     *
     * @return list<array{label: string, value: string, icon: Heroicon}>
     */
    private function stats(User $user): array
    {
        return [
            ['label' => 'نوشته', 'value' => $this->persian((string) Article::query()->where('author_id', $user->getKey())->count()), 'icon' => Heroicon::OutlinedNewspaper],
            ['label' => 'برگه', 'value' => $this->persian((string) PageModel::query()->where('author_id', $user->getKey())->count()), 'icon' => Heroicon::OutlinedDocumentText],
            ['label' => 'پاسخ به دیدگاه', 'value' => $this->persian((string) Comment::query()->where('user_id', $user->getKey())->count()), 'icon' => Heroicon::OutlinedChatBubbleLeftRight],
        ];
    }

    /**
     * The user's newest articles, linked to their edit page when the user may open it.
     *
     * @return list<array{title: string, date: string, live: bool, url: ?string}>
     */
    private function latest(User $user): array
    {
        $edit = ArticleResource::canViewAny();

        return Article::query()
            ->where('author_id', $user->getKey())
            ->latest('published_at')
            ->latest('id')
            ->limit(self::LATEST)
            ->get(['id', 'title', 'published_at'])
            ->map(fn (Article $article): array => [
                'title' => (string) $article->title,
                'date' => $article->published_at === null ? '—' : $this->persian(Jalalian::fromCarbon($article->published_at->copy()->timezone(Shamsi::ZONE))->format('j F Y')),
                'live' => $article->published_at !== null && $article->published_at->isPast(),
                'url' => $edit ? ArticleResource::getUrl('edit', ['record' => $article]) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * The text with Persian digits.
     */
    private function persian(string $text): string
    {
        return CalendarUtils::convertNumbers($text);
    }
}
