<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Entries\EntryResource;
use App\Filament\Resources\Forms\FormResource;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Setting;
use App\Support\Shamsi;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

/**
 * The banner at the top of the dashboard: a greeting for the time of day in Tehran, today's
 * Jalali date, and shortcuts to the pages the signed-in user may open.
 *
 * Extending:
 * - Add a shortcut in shortcuts. Each one is kept only when its resource allows the user.
 * - The look lives in resources/css/panel.css under dp-welcome.
 * - Filament owns canView, getViewData, and the sort and column span properties.
 */
class Welcome extends Widget
{
    /** First on the dashboard, before the stats. */
    protected static ?int $sort = -3;

    /** Rendered with the page, so the banner never flashes in. */
    protected static bool $isLazy = false;

    /** The banner runs the full width of the dashboard grid. */
    protected int|string|array $columnSpan = 'full';

    /** @var view-string */
    protected string $view = 'filament.widgets.welcome';

    /**
     * Shown to anyone signed in to the panel.
     *
     * Filament owns this method name.
     */
    public static function canView(): bool
    {
        return Filament::auth()->check();
    }

    /**
     * The greeting, name, date, and shortcuts for the view.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $now = now(Shamsi::ZONE);
        $user = Filament::auth()->user();

        return [
            'greeting' => $this->greeting((int) $now->format('G')),
            'name' => $user === null ? '' : Filament::getUserName($user),
            'date' => CalendarUtils::convertNumbers(Jalalian::fromCarbon($now)->format('l j F Y')),
            'brand' => Setting::brand(),
            'shortcuts' => $this->shortcuts(),
        ];
    }

    /**
     * صبح، ظهر، عصر، or شب بخیر for the given hour of the day.
     */
    private function greeting(int $hour): string
    {
        return match (true) {
            $hour >= 5 && $hour < 12 => 'صبح بخیر',
            $hour >= 12 && $hour < 16 => 'ظهر بخیر',
            $hour >= 16 && $hour < 20 => 'عصر بخیر',
            default => 'شب بخیر',
        };
    }

    /**
     * Links to common tasks, keeping only those the user may open.
     *
     * @return list<array{label: string, icon: Heroicon, url: string}>
     */
    private function shortcuts(): array
    {
        $links = [];

        if (ArticleResource::canCreate()) {
            $links[] = ['label' => 'نوشته تازه', 'icon' => Heroicon::OutlinedPencilSquare, 'url' => ArticleResource::getUrl('create')];
        }

        if (PageResource::canCreate()) {
            $links[] = ['label' => 'برگه تازه', 'icon' => Heroicon::OutlinedDocumentPlus, 'url' => PageResource::getUrl('create')];
        }

        if (FormResource::canCreate()) {
            $links[] = ['label' => 'فرم تازه', 'icon' => Heroicon::OutlinedClipboardDocumentList, 'url' => FormResource::getUrl('create')];
        }

        if (EntryResource::canViewAny()) {
            $links[] = ['label' => 'صندوق پیام‌ها', 'icon' => Heroicon::OutlinedInboxArrowDown, 'url' => EntryResource::getUrl('index')];
        }

        return $links;
    }
}
