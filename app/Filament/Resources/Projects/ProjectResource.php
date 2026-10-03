<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Fields\MediaPicker;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Schemas\Editor;
use App\Models\Article;
use App\Models\Project;
use App\Support\Shamsi;
use App\Support\Sizes;
use Damoon\Schema\Filament\SchemaEditor;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
 * The panel projects section, in the content group.
 *
 * Each project has a title, slug, description, the client's brand logo, the year it was
 * done, the services given, the client's industry, how long it took, where it was built,
 * the client's testimonial, similar projects, an SEO box, a schema box (SchemaEditor, starting with
 * Project::SCHEMAS), and a switch that allows comments.
 * The description editor is Editor::body, shared with articles, and is optional here.
 * The logo is a media library picture. The testimonial voice message is an audio file
 * uploaded to Project::VOICES on the public disk, so it shows up on the media page too.
 *
 * Extending:
 * - Add a field here and on the projects migration together.
 * - Filament owns the form, table, and getPages method names.
 */
class ProjectResource extends Resource
{
    use HasSEOFields;

    /** The Eloquent model this page lists, creates, and edits. */
    protected static ?string $model = Project::class;

    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'پروژه‌ها';

    /** The singular name used in buttons and headings, such as "ایجاد پروژه". */
    protected static ?string $modelLabel = 'پروژه';

    /** The plural name used for the list page title and breadcrumbs. */
    protected static ?string $pluralModelLabel = 'پروژه‌ها';

    /** The menu group this item sits in. */
    protected static string|\UnitEnum|null $navigationGroup = 'محتوا';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    /** The position inside the content group, after brands. */
    protected static ?int $navigationSort = 4;

    /** The column used as the record's title in global search and breadcrumbs. */
    protected static ?string $recordTitleAttribute = 'title';

    /** The URL segment after /admin. */
    protected static ?string $slug = 'projects';

    /**
     * Create and edit form for a project.
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
            Editor::body(Project::FOLDER, Project::BLOCKS)
                ->label('توضیحات')
                ->required(false),
            FormSection::make('مشخصات پروژه')
                ->columnSpan(2)
                ->columns(2)
                ->schema([
                    MediaPicker::make('logo')
                        ->label('لوگوی برند')
                        ->directory(Project::LOGOS)
                        ->columnSpanFull(),
                    TextInput::make('year')
                        ->label('سال اجرای پروژه')
                        ->integer()
                        ->minValue(Project::EARLIEST)
                        ->maxValue(Project::LATEST)
                        ->placeholder('۱۴۰۳')
                        ->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('industry')
                        ->label('صنعت فعالیت')
                        ->maxLength(255),
                    TextInput::make('duration')
                        ->label('مدت اجرا')
                        ->maxLength(255)
                        ->placeholder('مثلاً ۶ ماه'),
                    TextInput::make('location')
                        ->label('محل احداث')
                        ->maxLength(255),
                    TagsInput::make('services')
                        ->label('خدمات ارائه شده')
                        ->placeholder('نام خدمت را بنویسید و Enter بزنید')
                        ->reorderable()
                        ->columnSpanFull(),
                ]),
            FormSection::make('رضایت کارفرما')
                ->columnSpan(2)
                ->columns(2)
                ->schema([
                    TextInput::make('employer')
                        ->label('نام کارفرما')
                        ->maxLength(255),
                    TextInput::make('position')
                        ->label('سمت')
                        ->maxLength(255),
                    Textarea::make('testimony')
                        ->label('توضیحات')
                        ->rows(4)
                        ->columnSpanFull(),
                    FileUpload::make('voice')
                        ->label('فایل پیام صوتی')
                        ->helperText('فایل صوتی، حداکثر ۲۰ مگابایت. فایل در رسانه‌ها هم دیده می‌شود.')
                        ->disk('public')
                        ->directory(Project::VOICES)
                        ->visibility('public')
                        ->acceptedFileTypes(Project::TYPES)
                        ->maxSize(Project::WEIGHT)
                        ->downloadable()
                        ->openable()
                        ->columnSpanFull(),
                ]),
            Select::make('similar')
                ->label('پروژه‌های مشابه')
                ->relationship(name: 'similar', titleAttribute: 'title', ignoreRecord: true)
                ->multiple()
                ->searchable()
                ->preload()
                ->native(false)
                ->placeholder('انتخاب کنید')
                ->columnSpanFull(),
            static::seoSection(),
            SchemaEditor::section(),
            Toggle::make('commentable')
                ->label('اجازه به ارسال دیدگاه')
                ->default(true)
                ->columnSpanFull(),
        ]);
    }

    /**
     * Project list.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->label('لوگو')
                    ->getStateUsing(fn (Project $record): ?string => is_string($record->logo) && $record->logo !== ''
                        ? url(Sizes::url($record->logo, 'thumb'))
                        : null)
                    ->imageHeight(40),
                TextColumn::make('title')
                    ->label('عنوان')
                    ->description(fn (Project $record): ?string => $record->location)
                    ->searchable(['title', 'industry', 'location']),
                TextColumn::make('industry')->label('صنعت فعالیت')->placeholder('—'),
                TextColumn::make('year')->label('سال اجرا')->placeholder('—')->sortable(),
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
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }
}
