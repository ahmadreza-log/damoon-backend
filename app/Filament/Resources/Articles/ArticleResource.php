<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Fields\MediaPicker;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Tags\TagResource;
use App\Filament\Schemas\Editor;
use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Support\Shamsi;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

/**
 * The panel articles section, in the content group.
 *
 * Each article has a title, slug, body, cover, categories, tags, author,
 * publish date, a switch that allows comments, an SEO box, questions, a gallery, and related articles and products.
 * The SEO box is seoSection() from rankbeam/laravel-seo-filament, fitted to the panel in Seo::boot.
 * The body editor is Editor::body, shared with pages. It saves Tiptap JSON,
 * offers the blocks in Article::BLOCKS, and stores pictures in Article::FOLDER.
 * The category and tag fields reuse the CategoryResource and TagResource forms
 * to create options, and link to those pages.
 *
 * Extending:
 * - Add a field here and on the articles migration together.
 * - Filament owns the form, table, and getPages method names.
 */
class ArticleResource extends Resource
{
    use HasSEOFields;

    /** The Eloquent model this page lists, creates, and edits. */
    protected static ?string $model = Article::class;

    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'نوشته‌ها';

    /** The singular name used in buttons and headings, such as "ایجاد نوشته". */
    protected static ?string $modelLabel = 'نوشته';

    /** The plural name used for the list page title and breadcrumbs. */
    protected static ?string $pluralModelLabel = 'نوشته‌ها';

    /** The menu group this item sits in; categories and tags nest under it. */
    protected static string|\UnitEnum|null $navigationGroup = 'محتوا';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    /** The position inside the content group; lower numbers come first. */
    protected static ?int $navigationSort = 1;

    /** The column used as the record's title in global search and breadcrumbs. */
    protected static ?string $recordTitleAttribute = 'title';

    /** The URL segment after /admin; category and tag pages build on it. */
    protected static ?string $slug = 'articles';

    /**
     * Create and edit form for an article.
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
            Editor::body(Article::FOLDER, Article::BLOCKS),
            FormSection::make('تصاویر')
                ->columnSpan(2)
                ->schema([
                    MediaPicker::make('cover')
                        ->label('تصویر شاخص')
                        ->directory('articles/covers'),
                    MediaPicker::make('gallery')
                        ->label('گالری تصاویر')
                        ->multiple()
                        ->directory('articles/gallery')
                        ->helperText('ترتیب تصاویر را با کشیدن عوض کنید.')
                        ->columnSpanFull(),
                ]),
            Select::make('categories')
                ->label('دسته‌بندی')
                ->relationship('categories', 'name', fn (Builder $query): Builder => $query->with('parent'))
                ->getOptionLabelFromRecordUsing(fn (Category $record): string => $record->trail())
                ->multiple()
                ->searchable()
                ->preload()
                ->native(false)
                ->placeholder('انتخاب کنید')
                ->createOptionForm(fn (Schema $schema): Schema => CategoryResource::form($schema))
                ->createOptionModalHeading('ایجاد دسته‌بندی')
                ->manageOptionActions(fn (Action $action): Action => $action->modalWidth(Width::FourExtraLarge))
                ->hintAction(self::manage('categories', 'مدیریت دسته‌بندی‌ها', CategoryResource::class)),
            Select::make('tags')
                ->label('برچسب')
                ->relationship('tags', 'name', fn (Builder $query): Builder => $query->with('parent'))
                ->getOptionLabelFromRecordUsing(fn (Tag $record): string => $record->trail())
                ->multiple()
                ->searchable()
                ->preload()
                ->native(false)
                ->placeholder('انتخاب کنید')
                ->createOptionForm(fn (Schema $schema): Schema => TagResource::form($schema))
                ->createOptionModalHeading('ایجاد برچسب')
                ->manageOptionActions(fn (Action $action): Action => $action->modalWidth(Width::FourExtraLarge))
                ->hintAction(self::manage('tags', 'مدیریت برچسب‌ها', TagResource::class)),
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
            FormSection::make('سوالات متداول')
                ->columnSpan(2)
                ->schema([
                    Repeater::make('questions')
                        ->label('سوالات')
                        ->schema([
                            TextInput::make('question')->label('سوال')->required()->maxLength(255),
                            Textarea::make('answer')->label('پاسخ')->required()->rows(3),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel('افزودن سوال')
                        ->columnSpanFull(),
                ]),
            FormSection::make('مرتبط')
                ->description('مقالات و محصولات مرتبط با این نوشته.')
                ->columnSpan(2)
                ->schema([
                    Select::make('related')
                        ->label('مقالات مرتبط')
                        ->relationship(name: 'related', titleAttribute: 'title', ignoreRecord: true)
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->placeholder('انتخاب کنید'),
                    Select::make('products')
                        ->label('محصولات مرتبط')
                        ->relationship('products', 'title')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->placeholder('انتخاب کنید')
                        ->createOptionForm([
                            TextInput::make('title')->label('عنوان')->required()->maxLength(255)->unique(table: 'products', column: 'title'),
                        ])
                        ->createOptionModalHeading('ایجاد محصول'),
                ]),
        ]);
    }

    /**
     * Article list.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('categories.name')->label('دسته‌بندی')->badge()->placeholder('—'),
                TextColumn::make('author.firstname')
                    ->label('نویسنده')
                    ->formatStateUsing(fn (?string $state, Article $record): string => $record->author instanceof User
                        ? $record->author->getFilamentName()
                        : '—'),
                TextColumn::make('published_at')->label('تاریخ انتشار')->jalaliDateTime(timezone: Shamsi::ZONE),
            ])
            ->defaultSort('published_at', 'desc')
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
     * Loads the categories and author for the list.
     *
     * @return Builder<Article>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['categories', 'author']);
    }

    /**
     * Index, create, and edit pages.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }

    /**
     * A link above a field to the list page of a resource, opened in a new tab
     * so the article being written is not lost.
     *
     * @param  class-string<resource>  $resource
     */
    private static function manage(string $name, string $label, string $resource): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->url(fn (): string => $resource::getUrl('index'))
            ->openUrlInNewTab()
            ->visible(fn (): bool => $resource::canViewAny());
    }
}
