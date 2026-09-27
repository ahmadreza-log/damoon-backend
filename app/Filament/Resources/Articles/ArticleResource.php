<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\User;
use App\Support\Shamsi;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

/**
 * The panel articles section, in the content group.
 *
 * Each article has a title, slug, body, cover, category, tags, author,
 * publish date, an SEO box, questions, a gallery, and related articles and products.
 *
 * Extending:
 * - Add a field here and on the articles migration together.
 * - Filament owns the form, table, and getPages method names.
 */
class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationLabel = 'نوشته‌ها';

    protected static ?string $modelLabel = 'نوشته';

    protected static ?string $pluralModelLabel = 'نوشته‌ها';

    protected static string|\UnitEnum|null $navigationGroup = 'محتوا';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

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
            RichEditor::make('content')
                ->label('محتوا')
                ->required()
                ->columnSpanFull(),
            FormSection::make('تصاویر')
                ->columnSpan(2)
                ->schema([
                    FileUpload::make('cover')
                        ->label('تصویر شاخص')
                        ->image()
                        ->disk('public')
                        ->directory('articles/covers')
                        ->visibility('public')
                        ->nullable()
                        ->maxSize(4096),
                    FileUpload::make('gallery')
                        ->label('گالری تصاویر')
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->disk('public')
                        ->directory('articles/gallery')
                        ->visibility('public')
                        ->nullable()
                        ->maxSize(4096)
                        ->columnSpanFull(),
                ]),
            Select::make('category_id')
                ->label('دسته‌بندی')
                ->relationship('category', 'name')
                ->searchable()
                ->preload()
                ->native(false)
                ->placeholder('انتخاب کنید')
                ->createOptionForm([
                    TextInput::make('name')->label('نام')->required()->maxLength(255)->unique(table: 'categories', column: 'name'),
                ]),
            Select::make('tags')
                ->label('برچسب')
                ->relationship('tags', 'name')
                ->multiple()
                ->searchable()
                ->preload()
                ->native(false)
                ->placeholder('انتخاب کنید')
                ->createOptionForm([
                    TextInput::make('name')->label('نام')->required()->maxLength(255)->unique(table: 'tags', column: 'name'),
                ]),
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
            FormSection::make('سئو')
                ->columnSpan(2)
                ->schema([
                    TextInput::make('seo_title')->label('عنوان سئو')->maxLength(255),
                    Textarea::make('seo_description')->label('توضیحات سئو')->rows(3)->maxLength(500),
                ]),
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
                        ]),
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
                TextColumn::make('category.name')->label('دسته‌بندی')->placeholder('—'),
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
     * Loads the category and author for the list.
     *
     * @return Builder<Article>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['category', 'author']);
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
}
