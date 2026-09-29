<?php

namespace App\Filament\Resources\Comments;

use App\Filament\Resources\Comments\Pages\EditComment;
use App\Filament\Resources\Comments\Pages\ListComments;
use App\Models\Comment;
use App\Models\User;
use App\Support\Shamsi;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The panel comments section (دیدگاه‌ها), in the content group, like WordPress comments.
 *
 * Comments arrive from the site through the v1 API and wait as pending. Staff approve
 * them, mark them as spam, answer them, or edit and delete them. The list has a tab per
 * status, and the menu item shows how many are waiting.
 *
 * Extending:
 * - A new status needs a colour in colour() and a tab in ListComments.
 * - Filament owns the form, table, getEloquentQuery, getPages, and getNavigationBadge method names.
 */
class CommentResource extends Resource
{
    /** The Eloquent model this page lists and edits. */
    protected static ?string $model = Comment::class;

    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'دیدگاه‌ها';

    /** The singular name used in buttons and headings. */
    protected static ?string $modelLabel = 'دیدگاه';

    /** The plural name used for the list page title and breadcrumbs. */
    protected static ?string $pluralModelLabel = 'دیدگاه‌ها';

    /** The menu group this item sits in. */
    protected static string|\UnitEnum|null $navigationGroup = 'محتوا';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    /** The position inside the content group: after media. */
    protected static ?int $navigationSort = 4;

    /** The URL segment after /admin. */
    protected static ?string $slug = 'comments';

    /**
     * Edit form for a comment.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('نام')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('ایمیل')
                ->email()
                ->maxLength(255)
                ->extraInputAttributes(['dir' => 'ltr']),
            Textarea::make('body')
                ->label('متن دیدگاه')
                ->required()
                ->rows(6)
                ->maxLength(Comment::LIMIT)
                ->columnSpanFull(),
            Select::make('status')
                ->label('وضعیت')
                ->options(Comment::statuses())
                ->required()
                ->native(false),
        ]);
    }

    /**
     * Comment list, newest first.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('نویسنده')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Comment $record): string => $record->user_id !== null ? 'پاسخ مدیر' : (string) $record->email),
                TextColumn::make('body')
                    ->label('دیدگاه')
                    ->searchable()
                    ->limit(120)
                    ->wrap()
                    ->extraCellAttributes(['style' => 'min-width: 16rem'])
                    ->description(fn (Comment $record): ?string => $record->parent instanceof Comment ? 'در پاسخ به '.$record->parent->name : null, position: 'above')
                    ->description(fn (Comment $record): string => $record->kind().' «'.($record->subject?->getAttribute('title') ?? '—').'»'),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Comment::statuses()[$state] ?? $state)
                    ->color(fn (string $state): string => self::colour($state)),
                TextColumn::make('created_at')
                    ->label('تاریخ')
                    ->jalaliDateTime(timezone: Shamsi::ZONE)
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('subject_type')
                    ->label('نوع')
                    ->options(Comment::kinds())
                    ->native(false),
            ])
            ->emptyStateHeading('دیدگاهی نیست.')
            ->recordActions([
                self::reply()->iconButton(),
                Action::make('approve')
                    ->label('تأیید')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->iconButton()
                    ->visible(fn (Comment $record): bool => $record->status !== Comment::APPROVED && static::canEdit($record))
                    ->action(function (Comment $record, Action $action): void {
                        $record->mark(Comment::APPROVED);
                        $action->success();
                    })
                    ->successNotificationTitle('دیدگاه تأیید شد.'),
                ActionGroup::make([
                    Action::make('unapprove')
                        ->label('لغو تأیید')
                        ->icon(Heroicon::OutlinedClock)
                        ->visible(fn (Comment $record): bool => $record->status === Comment::APPROVED && static::canEdit($record))
                        ->action(function (Comment $record, Action $action): void {
                            $record->mark(Comment::PENDING);
                            $action->success();
                        })
                        ->successNotificationTitle('دیدگاه به صف انتظار برگشت.'),
                    Action::make('spam')
                        ->label('هرزنامه')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->visible(fn (Comment $record): bool => $record->status !== Comment::SPAM && static::canEdit($record))
                        ->action(function (Comment $record, Action $action): void {
                            $record->mark(Comment::SPAM);
                            $action->success();
                        })
                        ->successNotificationTitle('دیدگاه هرزنامه شد.'),
                    EditAction::make(),
                    DeleteAction::make()
                        ->modalDescription('پاسخ‌های این دیدگاه هم حذف می‌شوند.'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulk('approve', 'تأیید', Comment::APPROVED, Heroicon::OutlinedCheck),
                    self::bulk('unapprove', 'لغو تأیید', Comment::PENDING, Heroicon::OutlinedClock),
                    self::bulk('spam', 'هرزنامه', Comment::SPAM, Heroicon::OutlinedNoSymbol),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * The answer action: a staff reply published at once under the comment.
     */
    public static function reply(): Action
    {
        return Action::make('reply')
            ->label('پاسخ')
            ->icon(Heroicon::OutlinedChatBubbleLeft)
            ->color('primary')
            ->modalHeading(fn (Comment $record): string => 'پاسخ به '.$record->name)
            ->modalDescription(fn (Comment $record): string => Str::limit($record->body, 300))
            ->schema([
                Textarea::make('body')
                    ->label('متن پاسخ')
                    ->required()
                    ->rows(5)
                    ->maxLength(Comment::LIMIT),
            ])
            ->modalSubmitActionLabel('انتشار پاسخ')
            ->visible(fn (Comment $record): bool => $record->status !== Comment::SPAM && static::canEdit($record))
            ->action(function (Comment $record, array $data, Action $action): void {
                $user = Filament::auth()->user();

                if (! $user instanceof User) {
                    return;
                }

                $record->answer($user, (string) $data['body']);
                $action->success();
            })
            ->successNotificationTitle('پاسخ شما منتشر شد.');
    }

    /**
     * The badge colour of a status.
     */
    public static function colour(string $status): string
    {
        return match ($status) {
            Comment::APPROVED => 'success',
            Comment::SPAM => 'danger',
            default => 'warning',
        };
    }

    /**
     * The number of comments waiting for approval, shown next to the menu item.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Comment::query()->where('status', Comment::PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * The colour of the waiting count.
     */
    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    /**
     * The hint on the waiting count.
     */
    public static function getNavigationBadgeTooltip(): string
    {
        return 'در انتظار تأیید';
    }

    /**
     * Loads what each row shows next to the comment.
     *
     * @return Builder<Comment>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['subject', 'parent']);
    }

    /**
     * Index and edit pages; comments are written on the site, so there is no create page.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListComments::route('/'),
            'edit' => EditComment::route('/{record}/edit'),
        ];
    }

    /**
     * A bulk action that gives every selected comment one status.
     */
    private static function bulk(string $name, string $label, string $status, Heroicon $icon): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->action(function (Collection $records, BulkAction $action) use ($status): void {
                $records->each(fn (Comment $record) => $record->mark($status));
                $action->success();
            })
            ->successNotificationTitle('وضعیت دیدگاه‌ها به‌روز شد.')
            ->deselectRecordsAfterCompletion();
    }
}
