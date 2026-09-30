<?php

namespace App\Filament\Resources\Forms;

use App\Filament\Resources\Entries\EntryResource;
use App\Filament\Resources\Forms\Pages\CreateForm;
use App\Filament\Resources\Forms\Pages\DesignForm;
use App\Filament\Resources\Forms\Pages\EditForm;
use App\Filament\Resources\Forms\Pages\ListForms;
use App\Filament\Schemas\FormFields;
use App\Models\Article;
use App\Models\Entry;
use App\Models\Form;
use App\Models\FormSetting;
use App\Support\Shamsi;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The form builder (فرم‌ها), first in the forms group.
 *
 * A form has a title, a slug that is its address in the API, a description, its fields
 * built from FormFields blocks, the submit button text, the text shown after sending,
 * extra emails told about new messages, and an active switch. The list shows how many
 * messages each form has and links to them in the inbox. A form can be copied, which
 * makes an inactive copy with the same fields. The form builder (فرم‌ساز) is its own
 * full-screen page, DesignForm, opened with the designer action from the list and the edit
 * page; a new form opens in it right after it is created.
 *
 * Extending:
 * - Add a field here and on the forms migration together.
 * - Filament owns the form, table, getEloquentQuery, and getPages method names.
 */
class FormResource extends Resource
{
    /** The Eloquent model this page lists, creates, and edits. */
    protected static ?string $model = Form::class;

    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'فرم‌ها';

    /** The singular name used in buttons and headings, such as "ایجاد فرم". */
    protected static ?string $modelLabel = 'فرم';

    /** The plural name used for the list page title and breadcrumbs. */
    protected static ?string $pluralModelLabel = 'فرم‌ها';

    /** The menu group this item sits in. */
    protected static string|\UnitEnum|null $navigationGroup = 'فرم‌ها';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    /** The position inside the forms group: first. */
    protected static ?int $navigationSort = 1;

    /** The column used as the record's title in global search and breadcrumbs. */
    protected static ?string $recordTitleAttribute = 'title';

    /** The URL segment after /admin. */
    protected static ?string $slug = 'forms';

    /**
     * Create and edit form for a form.
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
                ->helperText('نشانی فرم در API: /v1/forms/{نامک}. اگر خالی بماند، از عنوان ساخته می‌شود.'),
            Textarea::make('description')
                ->label('توضیحات')
                ->rows(3)
                ->maxLength(2000)
                ->columnSpanFull(),
            FormSection::make('فیلدها')
                ->description('هر فیلد با کلیدش در API فرستاده و دریافت می‌شود. برای چیدن فیلدها با کشیدن و رها کردن و دیدن پیش‌نمایش زندهٔ فرم، دکمهٔ «فرم‌ساز» بالای همین صفحه را بزنید.')
                ->columnSpanFull()
                ->collapsible()
                ->collapsed(fn (string $operation): bool => $operation === 'edit')
                ->schema([
                    FormFields::make()->hiddenLabel(),
                ]),
            FormSection::make('پس از ارسال')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('button')
                        ->label('متن دکمه ارسال')
                        ->placeholder(Form::BUTTON)
                        ->maxLength(60),
                    TagsInput::make('recipients')
                        ->label('ایمیل‌های دریافت اعلان')
                        ->helperText('افزون بر ایمیل‌های تنظیمات فرم‌ها.')
                        ->placeholder('ایمیل را بنویسید و Enter بزنید')
                        ->nestedRecursiveRules(['email', 'max:255']),
                    Textarea::make('message')
                        ->label('پیام پس از ارسال')
                        ->placeholder(fn (): string => FormSetting::current()->thanks())
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    Toggle::make('active')
                        ->label('فعال')
                        ->helperText('فرم غیرفعال در API دیده نمی‌شود و پیام نمی‌گیرد.')
                        ->default(true),
                ]),
        ]);
    }

    /**
     * The link that opens the full-screen form builder for one form.
     */
    public static function designer(): Action
    {
        return Action::make('design')
            ->label('فرم‌ساز')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('primary')
            ->url(fn (Form $record): string => static::getUrl('design', ['record' => $record]))
            ->visible(fn (Form $record): bool => static::canEdit($record));
    }

    /**
     * Form list.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->description(fn (Form $record): string => '/v1/forms/'.$record->slug)
                    ->searchable(['title', 'slug'])
                    ->weight('medium'),
                TextColumn::make('fields')
                    ->label('فیلدها')
                    ->state(fn (Form $record): int => count(array_filter($record->definition(), fn (array $field): bool => $field['key'] !== null))),
                TextColumn::make('entries_count')
                    ->label('پیام‌ها')
                    ->badge()
                    ->color(fn (Form $record): string => ($record->getAttribute('fresh_count') ?? 0) > 0 ? 'warning' : 'gray')
                    ->description(fn (Form $record): ?string => ($record->getAttribute('fresh_count') ?? 0) > 0 ? $record->getAttribute('fresh_count').' جدید' : null)
                    ->sortable(),
                ToggleColumn::make('active')
                    ->label('فعال')
                    ->disabled(fn (Form $record): bool => ! static::canEdit($record)),
                TextColumn::make('updated_at')->label('آخرین ویرایش')->jalaliDateTime(timezone: Shamsi::ZONE),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('فرمی نیست.')
            ->recordActions([
                Action::make('inbox')
                    ->label('پیام‌ها')
                    ->icon(Heroicon::OutlinedInbox)
                    ->color('gray')
                    ->visible(fn (): bool => EntryResource::canViewAny())
                    ->url(fn (Form $record): string => EntryResource::getUrl('index', ['filters' => ['form_id' => ['value' => $record->getKey()]]])),
                self::designer(),
                EditAction::make(),
                ActionGroup::make([
                    ReplicateAction::make()
                        ->label('رونوشت')
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->excludeAttributes(['slug', 'entries_count', 'fresh_count'])
                        ->beforeReplicaSaved(function (Form $replica): void {
                            $replica->title = $replica->title.' (رونوشت)';
                            $replica->active = false;
                        })
                        ->successNotificationTitle('رونوشت فرم ساخته شد.'),
                    DeleteAction::make()
                        ->modalDescription('پیام‌های این فرم و فایل‌هایشان هم حذف می‌شوند.'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Counts every message and the new ones for the list.
     *
     * @return Builder<Form>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount([
            'entries',
            'entries as fresh_count' => fn (Builder $query): Builder => $query->where('status', Entry::NEW),
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
            'index' => ListForms::route('/'),
            'create' => CreateForm::route('/create'),
            'edit' => EditForm::route('/{record}/edit'),
            'design' => DesignForm::route('/{record}/design'),
        ];
    }
}
