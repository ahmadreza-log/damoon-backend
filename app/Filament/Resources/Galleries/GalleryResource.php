<?php

namespace App\Filament\Resources\Galleries;

use App\Filament\Fields\MediaPicker;
use App\Filament\Resources\Galleries\Pages\CreateGallery;
use App\Filament\Resources\Galleries\Pages\EditGallery;
use App\Filament\Resources\Galleries\Pages\ListGalleries;
use App\Models\Article;
use App\Models\Gallery;
use App\Models\Kind;
use App\Support\Shamsi;
use App\Support\Sizes;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The panel galleries section, in the content group: picture, video, and audio galleries.
 *
 * Each gallery has a title, slug, kind, a short description, and its files. The kind is chosen
 * when the gallery is made and stays fixed, so its files always match it. The files field is
 * MediaPicker with that kind: its library tab lists only files of the kind, and its upload tab
 * accepts only the kind's formats (Kind::types) and stores new files in Gallery::folder(kind),
 * so they show up on the media page too. Files are never typed in as links.
 *
 * Extending:
 * - Add a field here and on the galleries migration together.
 * - Filament owns the form, table, and getPages method names.
 */
class GalleryResource extends Resource
{
    /** The Eloquent model this page lists, creates, and edits. */
    protected static ?string $model = Gallery::class;

    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'گالری‌ها';

    /** The singular name used in buttons and headings, such as "ایجاد گالری". */
    protected static ?string $modelLabel = 'گالری';

    /** The plural name used for the list page title and breadcrumbs. */
    protected static ?string $pluralModelLabel = 'گالری‌ها';

    /** The menu group this item sits in. */
    protected static string|\UnitEnum|null $navigationGroup = 'محتوا';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    /** The position inside the content group, after projects and before the media library. */
    protected static ?int $navigationSort = 5;

    /** The column used as the record's title in global search and breadcrumbs. */
    protected static ?string $recordTitleAttribute = 'title';

    /** The URL segment after /admin. */
    protected static ?string $slug = 'galleries';

    /**
     * Create and edit form for a gallery.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('عنوان')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                    if (filled($get('slug'))) {
                        return;
                    }

                    $set('slug', Article::link((string) $state));
                }),
            TextInput::make('slug')
                ->label('نامک')
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('اگر خالی بماند، از عنوان ساخته می‌شود.'),
            ToggleButtons::make('kind')
                ->label('نوع گالری')
                ->options(Kind::options())
                ->icons(Kind::icons())
                ->inline()
                ->required()
                ->default(Kind::Image->value)
                ->live()
                ->disabledOn('edit')
                ->helperText(fn (string $operation): string => $operation === 'edit'
                    ? 'نوع گالری پس از ساخت عوض نمی‌شود.'
                    : 'بارگذاری و انتخاب از رسانه‌ها فقط فرمت‌های همین نوع را می‌پذیرد.')
                ->afterStateUpdated(function (Set $set): void {
                    $set('items', []);
                })
                ->columnSpanFull(),
            Textarea::make('description')
                ->label('توضیحات')
                ->rows(3)
                ->maxLength(2000)
                ->columnSpanFull(),
            MediaPicker::make('items')
                ->label(fn (Get $get, ?Gallery $record): string => 'فایل‌های '.self::kind($get('kind'), $record)->title())
                ->multiple()
                ->required()
                ->kind(fn (Get $get, ?Gallery $record): Kind => self::kind($get('kind'), $record))
                ->directory(fn (Get $get, ?Gallery $record): string => Gallery::folder(self::kind($get('kind'), $record)))
                ->helperText('ترتیب فایل‌ها را با کشیدن عوض کنید.')
                ->columnSpanFull(),
        ]);
    }

    /**
     * Gallery list.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover')
                    ->label('پیش‌نمایش')
                    ->getStateUsing(fn (Gallery $record): ?string => $record->kind === Kind::Image && $record->paths() !== []
                        ? url(Sizes::url($record->paths()[0], 'thumb'))
                        : null)
                    ->imageHeight(40),
                TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable(['title', 'description'])
                    ->wrap()
                    ->description(fn (Gallery $record): ?string => $record->description !== null ? str($record->description)->limit(80)->toString() : null),
                TextColumn::make('kind')
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(fn (Kind $state): string => $state->title())
                    ->icon(fn (Kind $state): Heroicon => $state->icon())
                    ->color(fn (Kind $state): string => match ($state) {
                        Kind::Image => 'info',
                        Kind::Video => 'warning',
                        Kind::Audio => 'success',
                    })
                    ->description(fn (Gallery $record): string => count($record->paths()).' فایل'),
                TextColumn::make('updated_at')->label('آخرین ویرایش')->jalaliDateTime(timezone: Shamsi::ZONE)->sortable(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('نوع')
                    ->options(Kind::options()),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('هنوز گالری‌ای ساخته نشده است.')
            ->emptyStateDescription('گالری تصاویر، ویدئو یا صدا بسازید و فایل‌هایش را از رسانه‌ها انتخاب یا بارگذاری کنید.')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Index, create, and edit pages.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListGalleries::route('/'),
            'create' => CreateGallery::route('/create'),
            'edit' => EditGallery::route('/{record}/edit'),
        ];
    }

    /**
     * The gallery's kind: the saved one on edit, since the kind field is not sent there, otherwise the one chosen on the form.
     */
    private static function kind(mixed $state, ?Gallery $record): Kind
    {
        if ($record?->kind instanceof Kind) {
            return $record->kind;
        }

        return $state instanceof Kind ? $state : (Kind::tryFrom((string) $state) ?? Kind::Image);
    }
}
