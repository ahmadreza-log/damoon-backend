<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Comments\CommentResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Entries\EntryResource;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Entry;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Morilog\Jalali\CalendarUtils;

/**
 * The row of figures on the dashboard: new messages, comments waiting for review, published
 * articles, and customers, each with a two-week line of daily activity.
 *
 * A figure is shown only when its resource lets the signed-in user in, and the widget hides
 * itself when none is left.
 *
 * Extending:
 * - Add a figure in getStats behind its resource's canViewAny, with a line from trend and a colour from tone.
 * - The card look lives in resources/css/panel.css under dp-stat.
 * - Filament owns canView, getStats, and the sort property.
 */
class Overview extends StatsOverviewWidget
{
    /** Right after the welcome banner. */
    protected static ?int $sort = -2;

    /** Rendered with the page, so the figures never flash in. */
    protected static bool $isLazy = false;

    /** Counted once per visit; Filament would otherwise re-run every query each five seconds. */
    protected ?string $pollingInterval = null;

    /** Days covered by each activity line. */
    private const DAYS = 14;

    /**
     * Shown when at least one figure is open to the user.
     *
     * Filament owns this method name.
     */
    public static function canView(): bool
    {
        return EntryResource::canViewAny()
            || CommentResource::canViewAny()
            || ArticleResource::canViewAny()
            || CustomerResource::canViewAny();
    }

    /**
     * The figures the user may see.
     *
     * Filament owns this method name.
     *
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $stats = [];

        if (EntryResource::canViewAny()) {
            $stats[] = $this->tone(Stat::make('پیام‌های تازه', $this->count(Entry::query()->where('status', Entry::NEW)))
                ->description($this->count(Entry::query()).' پیام در کل')
                ->descriptionIcon(Heroicon::OutlinedInboxArrowDown)
                ->icon(Heroicon::OutlinedEnvelope)
                ->chart($this->trend(Entry::query()))
                ->url(EntryResource::getUrl('index')), 'primary');
        }

        if (CommentResource::canViewAny()) {
            $stats[] = $this->tone(Stat::make('دیدگاه‌های در انتظار', $this->count(Comment::query()->where('status', Comment::PENDING)))
                ->description($this->count(Comment::query()->where('status', Comment::APPROVED)).' دیدگاه تأییدشده')
                ->descriptionIcon(Heroicon::OutlinedCheckBadge)
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->chart($this->trend(Comment::query()))
                ->url(CommentResource::getUrl('index')), 'warning');
        }

        if (ArticleResource::canViewAny()) {
            $stats[] = $this->tone(Stat::make('نوشته‌های منتشرشده', $this->count(Article::query()->published()))
                ->description($this->count(Article::query()).' نوشته در کل')
                ->descriptionIcon(Heroicon::OutlinedDocumentText)
                ->icon(Heroicon::OutlinedNewspaper)
                ->chart($this->trend(Article::query()))
                ->url(ArticleResource::getUrl('index')), 'success');
        }

        if (CustomerResource::canViewAny()) {
            $stats[] = $this->tone(Stat::make('مشتریان', $this->count(Customer::query()))
                ->description($this->count(Customer::query()->where('created_at', '>=', now()->subDays(self::DAYS))).' نفر در دو هفته اخیر')
                ->descriptionIcon(Heroicon::OutlinedArrowTrendingUp)
                ->icon(Heroicon::OutlinedUserGroup)
                ->chart($this->trend(Customer::query()))
                ->url(CustomerResource::getUrl('index')), 'info');
        }

        return $stats;
    }

    /**
     * Paints the figure's line and icon bubble in one of the panel colours.
     *
     * Filament's stat view reads only the chart colour, so the icon takes it through --dp-tone.
     */
    private function tone(Stat $stat, string $color): Stat
    {
        return $stat
            ->chartColor($color)
            ->extraAttributes(['class' => 'dp-stat', 'style' => "--dp-tone: var(--{$color}-500)"]);
    }

    /**
     * The row count, written with Persian digits and thousands separators.
     *
     * @param  Builder<*>  $query
     */
    private function count(Builder $query): string
    {
        return str_replace(',', '٬', CalendarUtils::convertNumbers(number_format($query->count())));
    }

    /**
     * Rows created on each of the last DAYS days, oldest first, with empty days as zero.
     *
     * @param  Builder<*>  $query
     * @return list<int>
     */
    private function trend(Builder $query): array
    {
        $start = now()->subDays(self::DAYS - 1)->startOfDay();

        $totals = $query
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $line = [];

        for ($day = 0; $day < self::DAYS; $day++) {
            $line[] = (int) ($totals[$start->copy()->addDays($day)->toDateString()] ?? 0);
        }

        return $line;
    }
}
