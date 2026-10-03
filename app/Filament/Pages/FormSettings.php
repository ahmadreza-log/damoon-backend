<?php

namespace App\Filament\Pages;

use App\Auth\Section;
use App\Models\FormSetting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The forms settings page (تنظیمات فرم‌ها), in the settings group after the social networks settings.
 *
 * It edits the one FormSetting row: the email notice for new messages and its addresses,
 * the default text shown after sending, how many messages one IP may send a minute, the
 * largest uploaded file, and how many days messages are kept. It needs the forms permission.
 *
 * Extending:
 * - A new setting is a field here and a column on FormSetting.
 * - Filament owns form, content, mount, and canAccess.
 *
 * @property-read Schema $form
 */
class FormSettings extends Page
{
    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'تنظیمات فرم‌ها';

    /** The page heading and browser tab title. */
    protected static ?string $title = 'تنظیمات فرم‌ها';

    /** The menu group this item sits in. */
    protected static string|\UnitEnum|null $navigationGroup = 'تنظیمات';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    /** The position inside the settings group: after the social networks settings. */
    protected static ?int $navigationSort = 3;

    /** The URL after /admin. */
    protected static ?string $slug = 'form-settings';

    /**
     * The form state.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Shows the page only for accounts that may open the form builder.
     *
     * Filament owns this method name.
     */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can(Section::FORMS);
    }

    /**
     * Fills the form with the saved settings, or the defaults.
     *
     * Livewire owns this method name.
     */
    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $settings = FormSetting::current();

        $this->form->fill([
            'notify' => $settings->notify,
            'recipients' => (array) $settings->recipients,
            'message' => $settings->message,
            'rate' => $settings->rate,
            'size' => $settings->size,
            'retention' => $settings->retention,
        ]);
    }

    /**
     * Where the form state is kept.
     *
     * Filament owns this method name.
     */
    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    /**
     * The settings fields.
     *
     * Filament owns this method name.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FormSection::make('اعلان پیام تازه')
                ->description('برای هر پیام تازه ایمیلی با پاسخ‌ها فرستاده می‌شود.')
                ->schema([
                    Toggle::make('notify')
                        ->label('ارسال اعلان')
                        ->live(),
                    TagsInput::make('recipients')
                        ->label('ایمیل‌های دریافت اعلان')
                        ->helperText('هر فرم می‌تواند ایمیل‌های خودش را هم داشته باشد.')
                        ->placeholder('ایمیل را بنویسید و Enter بزنید')
                        ->nestedRecursiveRules(['email', 'max:255']),
                ]),
            FormSection::make('ارسال')
                ->columns(2)
                ->schema([
                    Textarea::make('message')
                        ->label('پیام پیش‌فرض پس از ارسال')
                        ->placeholder(FormSetting::MESSAGE)
                        ->helperText('وقتی فرم پیام خودش را ندارد.')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    TextInput::make('rate')
                        ->label('بیشترین ارسال در دقیقه برای هر IP')
                        ->integer()
                        ->required()
                        ->minValue(FormSetting::RATES[0])
                        ->maxValue(FormSetting::RATES[1]),
                    TextInput::make('size')
                        ->label('بیشترین حجم فایل (کیلوبایت)')
                        ->helperText('سقف همهٔ فیلدهای فایل.')
                        ->integer()
                        ->required()
                        ->minValue(FormSetting::SIZES[0])
                        ->maxValue(FormSetting::SIZES[1]),
                    TextInput::make('retention')
                        ->label('نگهداری پیام‌ها (روز)')
                        ->helperText('پیام‌های قدیمی‌تر هر روز پاک می‌شوند. خالی یعنی همیشه نگه داشته شوند.')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(3650),
                ]),
        ]);
    }

    /**
     * The form with its save button.
     *
     * Filament owns this method name.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('ذخیره')->submit('save'),
                    ]),
                ]),
        ]);
    }

    /**
     * Writes the settings row, creating it on the first save.
     */
    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $settings = FormSetting::current();

        $settings->fill([
            'notify' => (bool) ($state['notify'] ?? false),
            'recipients' => array_values(array_filter((array) ($state['recipients'] ?? []), 'is_string')),
            'message' => filled($state['message'] ?? null) ? trim((string) $state['message']) : null,
            'rate' => (int) $state['rate'],
            'size' => (int) $state['size'],
            'retention' => filled($state['retention'] ?? null) ? (int) $state['retention'] : null,
        ])->save();

        Notification::make()->title('ذخیره شد')->success()->send();
    }
}
