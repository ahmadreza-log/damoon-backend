<?php

namespace App\Filament\Pages;

use App\Auth\Section;
use App\Filament\Pages\Asset as AssetPage;
use App\Models\User;
use App\Support\Library;
use App\Support\Shamsi;
use App\Support\Sizes;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * The media library in the content group.
 *
 * It shows every public file as a grid of cards, including avatars,
 * article images, and files uploaded on this page. Uploaded images get their
 * smaller copies from Sizes. Deleting a file also removes it from the user or
 * article that still points at it.
 *
 * Extending:
 * - Filament owns table, content, and canAccess.
 * - A new public folder gets its label in Library.
 */
class Media extends Page implements HasTable
{
    use InteractsWithTable;

    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'رسانه‌ها';

    /** The page heading and browser tab title. */
    protected static ?string $title = 'رسانه‌ها';

    /** The menu group shared with articles. */
    protected static string|\UnitEnum|null $navigationGroup = 'محتوا';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    /** The position inside the content group; the library comes after articles, pages, brands, and projects. */
    protected static ?int $navigationSort = 5;

    /** The URL after /admin; each file's detail page (Asset) lives under it at media/{asset}. */
    protected static ?string $slug = 'media';

    /**
     * Shows the library only for accounts that may open it.
     *
     * Filament owns this method name.
     */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can(Section::MEDIA);
    }

    /**
     * Upload sits above the grid.
     *
     * Filament owns this method name.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('بارگذاری')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->schema([
                    FileUpload::make('files')
                        ->label('فایل‌ها')
                        ->multiple()
                        ->required()
                        ->disk('public')
                        ->directory('media')
                        ->visibility('public')
                        ->acceptedFileTypes([
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                            'application/pdf',
                            'video/mp4',
                        ])
                        ->maxSize(10240),
                ])
                ->action(function (array $data): void {
                    foreach ((array) ($data['files'] ?? []) as $path) {
                        if (is_string($path)) {
                            Sizes::make($path);
                        }
                    }
                })
                ->successNotificationTitle('بارگذاری شد'),
        ];
    }

    /**
     * The file grid. Search and sort run on the rows from the public disk.
     */
    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, ?string $sortColumn, ?string $sortDirection): Collection => $this->sheet($search, $sortColumn, $sortDirection))
            ->modelLabel('رسانه')
            ->pluralModelLabel('رسانه‌ها')
            ->emptyStateHeading('رسانه‌ای نیست.')
            ->emptyStateDescription('فایل‌هایی که در پنل بارگذاری می‌شوند اینجا دیده می‌شوند.')
            ->paginated(false)
            ->searchable()
            ->contentGrid([
                'sm' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->columns([
                Stack::make([
                    ImageColumn::make('preview')
                        ->disk('public')
                        ->checkFileExistence(false)
                        ->imageHeight('11rem')
                        ->imageWidth('100%')
                        ->extraAttributes(['class' => 'w-full'])
                        ->extraImgAttributes([
                            'class' => 'object-cover',
                        ])
                        ->placeholder('فایل'),
                    TextColumn::make('name')
                        ->label('نام')
                        ->sortable()
                        ->weight(FontWeight::SemiBold)
                        ->size(TextSize::Medium)
                        ->alignCenter()
                        ->wrap()
                        ->extraAttributes(['style' => 'overflow-wrap:anywhere']),
                    TextColumn::make('place')
                        ->label('محل')
                        ->sortable()
                        ->size(TextSize::Small)
                        ->color('gray')
                        ->alignCenter(),
                    TextColumn::make('usage')
                        ->size(TextSize::Small)
                        ->color('gray')
                        ->alignCenter()
                        ->wrap(),
                    TextColumn::make('size')
                        ->label('حجم')
                        ->sortable()
                        ->size(TextSize::Small)
                        ->color('gray')
                        ->alignCenter()
                        ->formatStateUsing(fn (mixed $state): string => Library::weight((int) $state)),
                    TextColumn::make('modified')
                        ->label('تاریخ')
                        ->sortable()
                        ->size(TextSize::Small)
                        ->color('gray')
                        ->alignCenter()
                        ->jalaliDateTime(timezone: Shamsi::ZONE),
                ])->space(2)->alignment(Alignment::Center),
            ])
            ->recordActionsAlignment('center')
            ->recordUrl(fn (array $record): string => AssetPage::getUrl(['asset' => $record['__key']]))
            ->defaultSort('modified', 'desc')
            ->recordActions([
                Action::make('open')
                    ->label('جزئیات')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (array $record): string => AssetPage::getUrl(['asset' => $record['__key']])),
                Action::make('delete')
                    ->label('حذف')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('حذف رسانه')
                    ->modalDescription('این فایل حذف می‌شود. اگر روی یک کاربر یا نوشته باشد، از آنجا هم برداشته می‌شود.')
                    ->action(function (array $record): void {
                        Library::drop((string) ($record['path'] ?? ''));
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->label('حذف')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('حذف رسانه‌ها')
                        ->modalDescription('فایل‌های انتخاب‌شده حذف می‌شوند و از کاربر یا نوشته جدا می‌شوند.')
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                if (is_array($record)) {
                                    Library::drop((string) ($record['path'] ?? ''));
                                }
                            }
                        }),
                ]),
            ]);
    }

    /**
     * The table fills the page.
     *
     * Filament owns this method name.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    /**
     * Filters and sorts the library rows for the table.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function sheet(?string $search, ?string $sortColumn, ?string $sortDirection): Collection
    {
        $rows = collect(Library::rows());
        $needle = trim((string) $search);

        if ($needle !== '') {
            $rows = $rows->filter(function (array $row) use ($needle): bool {
                $haystack = $row['name'].' '.$row['title'].' '.$row['place'].' '.$row['usage'].' '.$row['path'];

                return mb_stripos($haystack, $needle) !== false;
            })->values();
        }

        $column = in_array($sortColumn, ['name', 'place', 'size', 'modified'], true) ? $sortColumn : 'modified';
        $descending = ($sortDirection ?? 'desc') !== 'asc';

        return $rows->sortBy($column, SORT_REGULAR, $descending)->values();
    }
}
