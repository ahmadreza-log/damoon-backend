<?php

namespace App\Filament\Resources\Tags;

use App\Filament\Fields\MediaPicker;
use App\Filament\Resources\Tags\Pages\CreateTag;
use App\Filament\Resources\Tags\Pages\EditTag;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Models\Article;
use App\Models\Tag;
use App\Support\Shamsi;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Article tags, shown under نوشته‌ها in the sidebar next to categories.
 *
 * Each tag has a name, slug, parent tag, description, sidebar banners,
 * and questions. The parent list hides the tag itself and everything under it.
 *
 * Extending:
 * - Add a field here and in a tags migration together.
 * - Filament owns the form, table, getEloquentQuery, and getPages method names.
 */
class TagResource extends Resource
{
    /** The Eloquent model this page lists, creates, and edits. */
    protected static ?string $model = Tag::class;

    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'برچسب‌ها';

    /** The singular name used in buttons and headings, such as "ایجاد برچسب". */
    protected static ?string $modelLabel = 'برچسب';

    /** The plural name used for the list page title and breadcrumbs. */
    protected static ?string $pluralModelLabel = 'برچسب‌ها';

    /** The menu group; it must match the parent item's group for the nesting to work. */
    protected static string|\UnitEnum|null $navigationGroup = 'محتوا';

    /** Shows this item nested under the articles menu item; it must equal that item's label. */
    protected static ?string $navigationParentItem = 'نوشته‌ها';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    /** The position under articles; tags come after categories. */
    protected static ?int $navigationSort = 2;

    /** The column used as the record's title in global search and breadcrumbs. */
    protected static ?string $recordTitleAttribute = 'name';

    /** The URL after /admin, placed under the articles URL to match the menu nesting. */
    protected static ?string $slug = 'articles/tags';

    /**
     * Create and edit form for a tag.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')
                ->label('نام')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
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
                ->helperText('اگر خالی بماند، از نام ساخته می‌شود.'),
            Select::make('parent_id')
                ->label('برچسب مادر')
                ->columnSpan(2)
                ->relationship(
                    name: 'parent',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn(Builder $query, ?Tag $record): Builder => $record?->exists
                        ? $query->whereNotIn('id', $record->family())
                        : $query,
                )
                ->searchable()
                ->preload()
                ->native(false)
                ->placeholder('بدون برچسب مادر'),
            Textarea::make('description')
                ->label('توضیح')
                ->rows(4)
                ->maxLength(5000)
                ->columnSpanFull(),
            Section::make('بنرهای سایدبار')
                ->description('تصویرهایی که کنار نوشته‌های این برچسب نمایش داده می‌شوند.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('banners')
                        ->label('بنرها')
                        ->hiddenLabel()
                        ->schema([
                            MediaPicker::make('image')
                                ->label('تصویر')
                                ->required()
                                ->directory(Tag::FOLDER)
                                ->columnSpanFull(),
                            TextInput::make('title')
                                ->label('عنوان')
                                ->maxLength(255),
                            TextInput::make('link')
                                ->label('لینک')
                                ->url()
                                ->maxLength(2048)
                                ->extraInputAttributes(['dir' => 'ltr']),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn(array $state): string => filled($state['title'] ?? null) ? (string) $state['title'] : 'بنر')
                        ->defaultItems(0)
                        ->addActionLabel('افزودن بنر'),
                ]),
            Section::make('سوالات متداول')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('questions')
                        ->label('سوالات')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('question')->label('سوال')->required()->maxLength(255),
                            Textarea::make('answer')->label('پاسخ')->required()->rows(3),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel('افزودن سوال'),
                ]),
        ]);
    }

    /**
     * Tag list.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable()->sortable(),
                TextColumn::make('slug')->label('نامک')->searchable(),
                TextColumn::make('parent.name')->label('برچسب مادر')->placeholder('—'),
                TextColumn::make('articles_count')->label('تعداد نوشته‌ها')->counts('articles')->sortable(),
                TextColumn::make('updated_at')->label('آخرین تغییر')->jalaliDateTime(timezone: Shamsi::ZONE)->sortable(),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('برچسبی نیست.')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('نوشته‌ها و زیربرچسب‌های این برچسب حذف نمی‌شوند، فقط از آن جدا می‌شوند.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Loads the parent tag for the list.
     *
     * @return Builder<Tag>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('parent');
    }

    /**
     * Index, create, and edit pages.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListTags::route('/'),
            'create' => CreateTag::route('/create'),
            'edit' => EditTag::route('/{record}/edit'),
        ];
    }
}
