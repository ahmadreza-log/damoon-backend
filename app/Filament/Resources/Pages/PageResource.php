<?php

namespace App\Filament\Resources\Pages;

use App\Filament\Fields\MediaPicker;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Schemas\Editor;
use App\Models\Article;
use App\Models\Page;
use App\Models\User;
use App\Support\Shamsi;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;
use Redberry\PageBuilderPlugin\Components\Forms\Actions\CreatePageBuilderBlockAction;
use Redberry\PageBuilderPlugin\Components\Forms\Actions\EditPageBuilderBlockAction;
use Redberry\PageBuilderPlugin\Components\Forms\Actions\SelectBlockAction;
use Redberry\PageBuilderPlugin\Components\Forms\PageBuilder;
use Redberry\PageBuilderPlugin\Components\Forms\PageBuilderPreview;

/**
 * The panel pages section (برگه‌ها), in the content group, like WordPress pages.
 *
 * Each page has a title, slug, body, cover, parent page, order, author, publish date,
 * and an SEO box. The body uses the same editor as articles, with pictures in Page::FOLDER.
 * The SEO box is seoSection() from rankbeam/laravel-seo-filament, fitted to the panel in Seo::boot.
 * Below it, the page builder (صفحه‌ساز) lays the page out from blocks, each edited in a
 * side panel with a live preview, and a collapsed box previews the whole layout.
 * The parent list shows each page's full trail and hides the page itself and everything under it.
 *
 * Extending:
 * - Add a field here and in a pages migration together.
 * - A new block goes in Page::BUILDER; the field lists it on its own.
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
                ->helperText('برای متن ساده کافی است. برای چیدن بخش‌هایی مثل بنر، گالری و سوالات از صفحه‌ساز پایین استفاده کنید.'),
            FormSection::make('صفحه‌ساز')
                ->description('بخش‌های برگه را اضافه کنید، با کشیدن جابه‌جا کنید، و هنگام ویرایش هر بخش پیش‌نمایش آن را ببینید.')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->columnSpanFull()
                ->schema([
                    self::builder(),
                ]),
            FormSection::make('پیش‌نمایش صفحه‌ساز')
                ->description('چیدمان فعلی بلوک‌ها، پیش از ذخیره.')
                ->icon(Heroicon::OutlinedEye)
                ->collapsible()
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    PageBuilderPreview::make('outline')
                        ->hiddenLabel()
                        ->pageBuilderField('builder'),
                ]),
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
            static::seoSection(),
        ]);
    }

    /**
     * The page builder field with Persian action labels.
     *
     * The package saves the blocks itself after the page is saved, so the field is not
     * part of the page's own data.
     */
    private static function builder(): PageBuilder
    {
        return PageBuilder::make('builder')
            ->label('بلوک‌ها')
            ->hiddenLabel()
            ->blocks(Page::BUILDER)
            ->reorderable()
            ->selectBlockAction(fn (SelectBlockAction $action): SelectBlockAction => $action
                ->label('افزودن بلوک')
                ->modalHeading('انتخاب بلوک')
                ->modalSubmitActionLabel('ادامه')
                ->selectField(fn (Select $field): Select => $field
                    ->label('نوع بلوک')
                    ->placeholder('یک بلوک انتخاب کنید')
                    ->native()))
            ->createAction(fn (CreatePageBuilderBlockAction $action): CreatePageBuilderBlockAction => $action
                ->label('افزودن')
                ->modalHeading('بلوک تازه')
                ->modalSubmitActionLabel('افزودن به برگه')
                ->successNotificationTitle('بلوک اضافه شد. برای ماندگاری، برگه را ذخیره کنید.')
                ->pageBuilderPreviewField(fn (PageBuilderPreview $field): PageBuilderPreview => $field->label('پیش‌نمایش')))
            ->editAction(fn (EditPageBuilderBlockAction $action): EditPageBuilderBlockAction => $action
                ->label('ویرایش')
                ->modalHeading('ویرایش بلوک')
                ->modalSubmitActionLabel('اعمال')
                ->successNotificationTitle('بلوک به‌روز شد. برای ماندگاری، برگه را ذخیره کنید.')
                ->pageBuilderPreviewField(fn (PageBuilderPreview $field): PageBuilderPreview => $field->label('پیش‌نمایش')));
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
        ];
    }
}
