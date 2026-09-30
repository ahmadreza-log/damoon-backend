<?php

namespace App\Filament\Resources\Entries;

use App\Filament\Resources\Entries\Pages\ListEntries;
use App\Filament\Resources\Entries\Pages\ViewEntry;
use App\Models\Entry;
use App\Support\Shamsi;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The inbox (صندوق پیام‌ها): messages sent through the forms, second in the forms group.
 *
 * Messages arrive from the site through the v1 API as new. Opening one marks it read;
 * staff can mark it unread again, archive it, or delete it. The list has a tab per status
 * and a filter per form, and the menu item shows how many messages are new. The export
 * button on the list downloads one form's messages as a CSV file. The message page is drawn
 * by Pages\ViewEntry and its own view.
 *
 * Extending:
 * - A new status needs a colour in colour() and a label in Entry::statuses.
 * - Filament owns the table, getEloquentQuery, getPages, and getNavigationBadge method names.
 */
class EntryResource extends Resource
{
    /** The Eloquent model this page lists and shows. */
    protected static ?string $model = Entry::class;

    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'صندوق پیام‌ها';

    /** The singular name used in buttons and headings. */
    protected static ?string $modelLabel = 'پیام';

    /** The plural name used for the list page title and breadcrumbs. */
    protected static ?string $pluralModelLabel = 'صندوق پیام‌ها';

    /** The menu group this item sits in. */
    protected static string|\UnitEnum|null $navigationGroup = 'فرم‌ها';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    /** The position inside the forms group: after the forms. */
    protected static ?int $navigationSort = 2;

    /** The URL segment after /admin. */
    protected static ?string $slug = 'inbox';

    /**
     * Message list, newest first.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('form.title')
                    ->label('فرم')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('summary')
                    ->label('پیام')
                    ->state(fn (Entry $record): string => $record->summary())
                    ->weight(fn (Entry $record): string => $record->status === Entry::NEW ? 'bold' : 'normal')
                    ->wrap()
                    ->extraCellAttributes(['style' => 'min-width: 16rem']),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Entry::statuses()[$state] ?? $state)
                    ->color(fn (string $state): string => self::colour($state)),
                TextColumn::make('created_at')
                    ->label('تاریخ')
                    ->jalaliDateTime(timezone: Shamsi::ZONE)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('form_id')
                    ->label('فرم')
                    ->relationship('form', 'title')
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->emptyStateHeading('پیامی نیست.')
            ->emptyStateDescription('پیام‌هایی که از فرم‌های سایت می‌رسند اینجا دیده می‌شوند.')
            ->recordActions([
                ViewAction::make()->iconButton(),
                ActionGroup::make([
                    self::status('read', 'خوانده شد', Entry::READ, Heroicon::OutlinedEnvelopeOpen),
                    self::status('unread', 'خوانده نشده', Entry::NEW, Heroicon::OutlinedEnvelope),
                    self::status('archive', 'بایگانی', Entry::ARCHIVED, Heroicon::OutlinedArchiveBox),
                    self::status('restore', 'خروج از بایگانی', Entry::READ, Heroicon::OutlinedArrowUturnLeft),
                    DeleteAction::make()
                        ->modalDescription('فایل‌های این پیام هم حذف می‌شوند.'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulk('read', 'خوانده شد', Entry::READ, Heroicon::OutlinedEnvelopeOpen),
                    self::bulk('unread', 'خوانده نشده', Entry::NEW, Heroicon::OutlinedEnvelope),
                    self::bulk('archive', 'بایگانی', Entry::ARCHIVED, Heroicon::OutlinedArchiveBox),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * An action that gives one message a status, shown only when it would change something.
     *
     * restore takes an archived message back to read; the others skip archived messages
     * except archive itself.
     */
    public static function status(string $name, string $label, string $status, Heroicon $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->visible(fn (Entry $record): bool => static::canEdit($record) && match ($name) {
                'restore' => $record->status === Entry::ARCHIVED,
                'archive' => $record->status !== Entry::ARCHIVED,
                default => $record->status !== Entry::ARCHIVED && $record->status !== $status,
            })
            ->action(function (Entry $record, Action $action) use ($status): void {
                $record->mark($status);
                $action->success();
            })
            ->successNotificationTitle('وضعیت پیام به‌روز شد.');
    }

    /**
     * The badge colour of a status.
     */
    public static function colour(string $status): string
    {
        return match ($status) {
            Entry::NEW => 'warning',
            Entry::ARCHIVED => 'gray',
            default => 'success',
        };
    }

    /**
     * The number of new messages, shown next to the menu item.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Entry::query()->where('status', Entry::NEW)->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * The colour of the new count.
     */
    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    /**
     * The hint on the new count.
     */
    public static function getNavigationBadgeTooltip(): string
    {
        return 'پیام‌های جدید';
    }

    /**
     * Loads the form and customer each row and page shows.
     *
     * @return Builder<Entry>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['form', 'customer']);
    }

    /**
     * Index and view pages; messages come from the site, so there is no create or edit page.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListEntries::route('/'),
            'view' => ViewEntry::route('/{record}'),
        ];
    }

    /**
     * A bulk action that gives every selected message one status.
     */
    private static function bulk(string $name, string $label, string $status, Heroicon $icon): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->action(function (Collection $records, BulkAction $action) use ($status): void {
                $records->each(fn (Entry $record) => $record->mark($status));
                $action->success();
            })
            ->successNotificationTitle('وضعیت پیام‌ها به‌روز شد.')
            ->deselectRecordsAfterCompletion();
    }
}
