<?php

namespace App\Filament\Resources\Brands;

use App\Filament\Fields\MediaPicker;
use App\Filament\Resources\Brands\Pages\CreateBrand;
use App\Filament\Resources\Brands\Pages\EditBrand;
use App\Filament\Resources\Brands\Pages\ListBrands;
use App\Filament\Schemas\Editor;
use App\Models\Article;
use App\Models\Brand;
use App\Support\Shamsi;
use App\Support\Sizes;
use Damoon\Schema\Filament\SchemaEditor;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

/**
 * The panel brands section, in the content group.
 *
 * Each brand has a title, slug, English title, description, a list of features, links to
 * its website, LinkedIn, and software download, a catalog file, a logo, an SEO box, a schema box
 * (SchemaEditor, starting with Brand::SCHEMAS), and a
 * switch that allows comments.
 * The description editor is Editor::body, shared with articles and pages, and is optional here.
 * The logo is a media library picture. The catalog is a PDF uploaded to Brand::CATALOGS
 * on the public disk, so it shows up on the media page too.
 *
 * Extending:
 * - Add a field here and on the brands migration together.
 * - Filament owns the form, table, and getPages method names.
 */
class BrandResource extends Resource
{
    use HasSEOFields;

    /** The Eloquent model this page lists, creates, and edits. */
    protected static ?string $model = Brand::class;

    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'برندها';

    /** The singular name used in buttons and headings, such as "ایجاد برند". */
    protected static ?string $modelLabel = 'برند';

    /** The plural name used for the list page title and breadcrumbs. */
    protected static ?string $pluralModelLabel = 'برندها';

    /** The menu group this item sits in. */
    protected static string|\UnitEnum|null $navigationGroup = 'محتوا';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    /** The position inside the content group, after articles and pages. */
    protected static ?int $navigationSort = 3;

    /** The column used as the record's title in global search and breadcrumbs. */
    protected static ?string $recordTitleAttribute = 'title';

    /** The URL segment after /admin. */
    protected static ?string $slug = 'brands';

    /**
     * Create and edit form for a brand.
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
            TextInput::make('english')
                ->label('عنوان انگلیسی')
                ->maxLength(255)
                ->extraInputAttributes(['dir' => 'ltr'])
                ->columnSpanFull(),
            Editor::body(Brand::FOLDER, Brand::BLOCKS)
                ->label('توضیحات')
                ->required(false),
            FormSection::make('ویژگی‌های برند')
                ->columnSpan(2)
                ->schema([
                    Repeater::make('features')
                        ->label('ویژگی‌ها')
                        ->schema([
                            TextInput::make('title')->label('عنوان ویژگی')->required()->maxLength(255),
                            Textarea::make('description')->label('توضیح')->rows(2),
                        ])
                        ->defaultItems(0)
                        ->reorderable()
                        ->addActionLabel('افزودن ویژگی')
                        ->columnSpanFull(),
                ]),
            FormSection::make('پیوندها')
                ->columnSpan(2)
                ->columns(2)
                ->schema([
                    TextInput::make('website')
                        ->label('وبسایت')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://')
                        ->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('linkedin')
                        ->label('لینکدین')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://www.linkedin.com/company/...')
                        ->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('download')
                        ->label('لینک دانلود نرم‌افزار')
                        ->url()
                        ->maxLength(255)
                        ->placeholder('https://')
                        ->extraInputAttributes(['dir' => 'ltr'])
                        ->columnSpanFull(),
                ]),
            FormSection::make('فایل‌ها')
                ->columnSpan(2)
                ->schema([
                    MediaPicker::make('logo')
                        ->label('لوگوی برند')
                        ->directory(Brand::LOGOS),
                    FileUpload::make('catalog')
                        ->label('کاتالوگ')
                        ->helperText('فایل PDF، حداکثر ۲۰ مگابایت. فایل در رسانه‌ها هم دیده می‌شود.')
                        ->disk('public')
                        ->directory(Brand::CATALOGS)
                        ->visibility('public')
                        ->acceptedFileTypes(Brand::TYPES)
                        ->maxSize(Brand::WEIGHT)
                        ->downloadable()
                        ->openable(),
                ]),
            static::seoSection(),
            SchemaEditor::section(),
            Toggle::make('commentable')
                ->label('اجازه به ارسال دیدگاه')
                ->default(true)
                ->columnSpanFull(),
        ]);
    }

    /**
     * Brand list.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->label('لوگو')
                    ->getStateUsing(fn (Brand $record): ?string => is_string($record->logo) && $record->logo !== ''
                        ? url(Sizes::url($record->logo, 'thumb'))
                        : null)
                    ->imageHeight(40),
                TextColumn::make('title')
                    ->label('عنوان')
                    ->description(fn (Brand $record): ?string => $record->english)
                    ->searchable(['title', 'english']),
                TextColumn::make('website')
                    ->label('وبسایت')
                    ->url(fn (Brand $record): ?string => $record->website)
                    ->openUrlInNewTab()
                    ->placeholder('—'),
                TextColumn::make('updated_at')->label('آخرین ویرایش')->jalaliDateTime(timezone: Shamsi::ZONE),
            ])
            ->defaultSort('updated_at', 'desc')
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
            'index' => ListBrands::route('/'),
            'create' => CreateBrand::route('/create'),
            'edit' => EditBrand::route('/{record}/edit'),
        ];
    }
}
