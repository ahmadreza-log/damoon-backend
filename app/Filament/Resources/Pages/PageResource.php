<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Fields\MediaPicker;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\DesignPage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Schemas\Editor;
use App\Models\Article;
use App\Models\Page;
use App\Models\User;
use App\Support\Shamsi;
use Damoon\Schema\Filament\SchemaEditor;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

/**
 * The panel pages section (برگه‌ها), in the content group, like WordPress pages.
 *
 * Each page has a title, slug, body, cover, parent page, order, author, publish date,
 * a switch that allows comments, and an SEO box. The body uses the same editor as articles, with pictures in Page::FOLDER.
 * The SEO box is seoSection() from rankbeam/laravel-seo-filament, fitted to the panel in Seo::boot.
 * The schema box under it is SchemaEditor from damoon/schema; it starts with Page::SCHEMAS.
 * The page builder (صفحه‌ساز) is its own full-width page, DesignPage, opened with the
 * designer action from the list and the edit page.
 * The parent list shows each page's full trail and hides the page itself and everything under it.
 *
 * Extending:
 * - Add a field here and in a pages migration together.
 * - A new page builder block goes in resources/js/designer.js.
 * - Filament owns the form, table, getEloquentQuery, and getPages method names.
 */
class PageResource extends Resource
{
    use HasSEOFields;

    /** The Eloquent model this page lists, creates, and edits. */
    protected static ?string $model = Page::class;

    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'برگه‌ها';

    /** The singular name used in buttons and headings, such as "ایجاد برگه". */
    protected static ?string $modelLabel = 'برگه';

    /** The plural name used for the list page title and breadcrumbs. */
    protected static ?string $pluralModelLabel = 'برگه‌ها';

    /** The menu group this item sits in. */
    protected static string|\UnitEnum|null $navigationGroup = 'محتوا';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;

    /** The position inside the content group: after articles, before media. */
    protected static ?int $navigationSort = 2;

    /** The column used as the record's title in global search and breadcrumbs. */
    protected static ?string $recordTitleAttribute = 'title';

    /** The URL segment after /admin. */
    protected static ?string $slug = 'pages';

    /**
     * Create and edit form for a page.
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
            Editor::body(Page::FOLDER, Page::BLOCKS)
                ->required(false)
                ->helperText('برای متن ساده کافی است. برای چیدن کامل برگه با کشیدن و رها کردن، پس از ذخیره دکمهٔ «صفحه‌ساز» بالای همین صفحه را بزنید.'),
            MediaPicker::make('cover')
                ->label('تصویر شاخص')
                ->directory(Page::COVERS)
                ->columnSpanFull(),
            Select::make('parent_id')
                ->label('برگهٔ مادر')
                ->relationship(
                    name: 'parent',
                    titleAttribute: 'title',
                    modifyQueryUsing: fn (Builder $query, ?Page $record): Builder => $record?->exists
                        ? $query->with('parent')->whereNotIn('id', $record->family())
                        : $query->with('parent'),
                )
                ->getOptionLabelFromRecordUsing(fn (Page $record): string => $record->trail())
                ->searchable()
                ->preload()
                ->native(false)
                ->placeholder('بدون برگهٔ مادر'),
            TextInput::make('position')
                ->label('ترتیب')
                ->integer()
                ->minValue(0)
                ->default(0)
                ->required()
                ->helperText('برگه‌هایی که یک مادر دارند، از عدد کمتر به بیشتر چیده می‌شوند.'),
            Select::make('author_id')
                ->label('نویسنده')
                ->relationship('author', 'username')
                ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->getFilamentName())
                ->searchable()
                ->preload()
                ->native(false)
                ->placeholder('انتخاب کنید')
                ->default(function (): mixed {
                    $user = Filament::auth()->user();

                    return $user instanceof User ? $user->getKey() : null;
                })
                ->required(),
            DateTimePicker::make('published_at')
                ->label('تاریخ انتشار')
                ->required()
                ->default(now()),
            Toggle::make('commentable')
                ->label('اجازه به ارسال دیدگاه')
                ->default(true)
                ->columnSpanFull(),
            static::seoSection(),
            SchemaEditor::section(),
        ]);
    }

    /**
     * The link that opens the page builder for one page.
     */
    public static function designer(): Action
    {
        return Action::make('design')
            ->label('صفحه‌ساز')
            ->icon(Heroicon::OutlinedPaintBrush)
            ->color('primary')
            ->url(fn (Page $record): string => static::getUrl('design', ['record' => $record]))
            ->visible(fn (Page $record): bool => static::canEdit($record));
    }

    /**
     * Page list, in the order the site shows them.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable()->sortable(),
                TextColumn::make('slug')->label('نامک')->searchable(),
                TextColumn::make('parent.title')->label('برگهٔ مادر')->placeholder('—'),
                TextColumn::make('position')->label('ترتیب')->sortable(),
                TextColumn::make('author.firstname')
                    ->label('نویسنده')
                    ->formatStateUsing(fn (?string $state, Page $record): string => $record->author instanceof User
                        ? $record->author->getFilamentName()
                        : '—'),
                TextColumn::make('published_at')->label('تاریخ انتشار')->jalaliDateTime(timezone: Shamsi::ZONE)->sortable(),
            ])
            ->defaultSort('position')
            ->emptyStateHeading('برگه‌ای نیست.')
            ->recordActions([
                self::designer(),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('زیربرگه‌های این برگه حذف نمی‌شوند، به بالاترین سطح منتقل می‌شوند.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Loads the parent page and author for the list.
     *
     * @return Builder<Page>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['parent', 'author']);
    }

    /**
     * Index, create, and edit pages.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
            'design' => DesignPage::route('/{record}/design'),
        ];
    }
}
