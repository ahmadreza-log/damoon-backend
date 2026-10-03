<?php

namespace App\Filament\Resources\ApiKeys;

use App\Filament\Resources\ApiKeys\Pages\ManageApiKeys;
use App\Models\ApiKey;
use App\Support\Shamsi;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;

/**
 * The API settings page (تنظیمات API), fourth in the settings group.
 *
 * Lists the private keys that open the /v1 API. Each key has a name, the one site origin whose
 * browser requests it opens, and an on/off switch. A new or regenerated key is shown once, in a
 * notification with a copy button; after that only its last characters are visible. Without at
 * least one active key the API answers every request with 401. It needs the api permission
 * (App\Policies\ApiKeyPolicy).
 *
 * Extending:
 * - A new rule on a key is a column, a field in form, and a check in ApiKey::allows.
 * - Filament owns the form, table, and getPages method names.
 */
class ApiKeyResource extends Resource
{
    /** The Eloquent model this page lists, creates, and edits. */
    protected static ?string $model = ApiKey::class;

    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'تنظیمات API';

    /** The singular name used in buttons and headings, such as "ایجاد کلید API". */
    protected static ?string $modelLabel = 'کلید API';

    /** The plural name used in the bulk actions. */
    protected static ?string $pluralModelLabel = 'کلیدهای API';

    /** The menu group this item sits in. */
    protected static string|\UnitEnum|null $navigationGroup = 'تنظیمات';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    /** The position inside the settings group: after the forms settings. */
    protected static ?int $navigationSort = 4;

    /** The URL after /admin. */
    protected static ?string $slug = 'settings/api';

    /** The column used as the record's title in headings. */
    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Create and edit form for a key, opened in a modal.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('نام')
                ->placeholder('مثلاً سایت اصلی')
                ->helperText('فقط برای شناختن کلید در این فهرست.')
                ->required()
                ->maxLength(100),
            TextInput::make('origin')
                ->label('دامنه (Origin)')
                ->placeholder('https://example.com')
                ->helperText(new HtmlString('نشانی سایتی که از مرورگر به API درخواست می‌دهد، مثل <bdi dir="ltr">https://example.com</bdi> یا <bdi dir="ltr">http://localhost:3000</bdi>. مسیر بعد از دامنه نادیده گرفته می‌شود.'))
                ->required()
                ->maxLength(255)
                ->extraInputAttributes(['dir' => 'ltr'])
                ->rules([fn (): Closure => self::origin(...)]),
            Toggle::make('active')
                ->label('فعال')
                ->helperText('کلید خاموش هیچ درخواستی را باز نمی‌کند.')
                ->default(true),
        ])->columns(1);
    }

    /**
     * The keys list.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('origin')->label('دامنه')->searchable()->fontFamily('mono')->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('hint')
                    ->label('کلید')
                    ->formatStateUsing(fn (string $state): string => ApiKey::PREFIX.'••••'.$state)
                    ->fontFamily('mono')
                    ->extraAttributes(['dir' => 'ltr']),
                ToggleColumn::make('active')->label('فعال'),
                TextColumn::make('used_at')->label('آخرین استفاده')->jalaliDateTime(timezone: Shamsi::ZONE)->placeholder('هنوز استفاده نشده'),
                TextColumn::make('created_at')->label('ساخته‌شده')->jalaliDateTime(timezone: Shamsi::ZONE)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('هنوز کلیدی ساخته نشده')
            ->emptyStateDescription('تا وقتی کلید فعالی نباشد، API به هیچ درخواستی پاسخ نمی‌دهد.')
            ->emptyStateIcon(Heroicon::OutlinedKey)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('regenerate')
                        ->label('کلید تازه')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('ساخت کلید تازه')
                        ->modalDescription('کلید فعلی همین حالا از کار می‌افتد و سایتی که از آن استفاده می‌کند باید کلید تازه را بگیرد.')
                        ->modalSubmitActionLabel('ساخت کلید تازه')
                        ->authorize(fn (ApiKey $record): bool => static::canEdit($record))
                        ->action(fn (ApiKey $record) => self::reveal($record->regenerate(), 'کلید تازه ساخته شد')),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Shows a plain key once, in a notification that stays until it is closed.
     */
    public static function reveal(string $plain, string $title = 'کلید API ساخته شد'): void
    {
        Notification::make()
            ->title($title)
            ->body(new HtmlString('<p>این کلید فقط همین یک بار نشان داده می‌شود؛ آن را کپی کنید و در سرآیند <bdi dir="ltr">X-Api-Key</bdi> بفرستید.</p><p><code dir="ltr">'.e($plain).'</code></p>'))
            ->success()
            ->persistent()
            ->actions([
                Action::make('copy')
                    ->label('کپی کلید')
                    ->icon(Heroicon::OutlinedClipboardDocument)
                    ->alpineClickHandler('window.navigator.clipboard.writeText('.Js::from($plain).'); $tooltip(\'کپی شد\', { timeout: 1500 })'),
            ])
            ->send();
    }

    /**
     * The single manage page; create and edit open in modals.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => ManageApiKeys::route('/'),
        ];
    }

    /**
     * Accepts only an http or https origin.
     */
    private static function origin(string $attribute, mixed $value, Closure $fail): void
    {
        if (ApiKey::normalize((string) $value) === null) {
            $fail('یک دامنهٔ درست با http:// یا https:// بنویسید، مثل https://example.com.');
        }
    }
}
