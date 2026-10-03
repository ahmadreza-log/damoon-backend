<?php

namespace App\Filament\Pages;

use App\Auth\Section;
use App\Models\Setting;
use App\Models\User;
use App\Support\Icons;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * The social networks settings page (تنظیمات شبکه‌های اجتماعی), second in the settings group.
 *
 * Its first box is a repeater of the site's social links. Each link has a name, an icon picked
 * from Blade Icons through App\Support\Icons, and an address. Links can be dragged into the
 * order the site shows them. Picking a well-known network fills an empty name with its Persian
 * name. The links are stored on the settings row and sent by /v1/socials. It needs the socials
 * permission.
 *
 * Extending:
 * - A new field on each link goes in the repeater here and in Setting::links.
 * - Filament owns form, content, mount, and canAccess.
 *
 * @property-read Schema $form
 */
class SocialSettings extends Page
{
    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'تنظیمات شبکه‌های اجتماعی';

    /** The page heading and browser tab title. */
    protected static ?string $title = 'تنظیمات شبکه‌های اجتماعی';

    /** The menu group this item sits in. */
    protected static string|\UnitEnum|null $navigationGroup = 'تنظیمات';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    /** The position inside the settings group: after the general settings. */
    protected static ?int $navigationSort = 2;

    /** The URL after /admin. */
    protected static ?string $slug = 'settings/socials';

    /** Most links the site keeps. */
    public const LIMIT = 30;

    /**
     * The form state.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Shows the page only for accounts with the socials section.
     *
     * Filament owns this method name.
     */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can(Section::SOCIALS);
    }

    /**
     * Fills the repeater with the saved links.
     *
     * Livewire owns this method name.
     */
    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'socials' => (array) Setting::current()?->socials,
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
     * The social links box.
     *
     * Filament owns this method name.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FormSection::make('لینک شبکه‌های اجتماعی')
                ->description('هر شبکه با نام، آیکن و لینکش. ترتیب این فهرست همان ترتیبی است که سایت نشان می‌دهد؛ برای جابه‌جایی، ردیف‌ها را بکشید.')
                ->icon(Heroicon::OutlinedLink)
                ->schema([
                    Repeater::make('socials')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('name')
                                ->label('نام')
                                ->placeholder('مثلاً اینستاگرام')
                                ->required()
                                ->maxLength(60)
                                ->live(onBlur: true),
                            Select::make('icon')
                                ->label('آیکن')
                                ->placeholder('جستجوی آیکن')
                                ->helperText('نام انگلیسی یا فارسی را بنویسید، مثل instagram یا ایتا.')
                                ->required()
                                ->searchable()
                                ->allowHtml()
                                ->options(fn (): array => Icons::options())
                                ->getSearchResultsUsing(fn (?string $search): array => Icons::search((string) $search))
                                ->getOptionLabelUsing(fn (?string $value): ?string => is_string($value) && Icons::exists($value) ? Icons::label($value) : null)
                                ->live()
                                ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                    if (blank($get('name')) && isset(Icons::POPULAR[$state])) {
                                        $set('name', Icons::POPULAR[$state]);
                                    }
                                }),
                            TextInput::make('url')
                                ->label('لینک')
                                ->placeholder('https://instagram.com/...')
                                ->helperText(new HtmlString('نشانی کامل با <bdi dir="ltr">https://</bdi>، یا <bdi dir="ltr">mailto:</bdi> و <bdi dir="ltr">tel:</bdi> برای ایمیل و تلفن.'))
                                ->required()
                                ->maxLength(2048)
                                ->extraInputAttributes(['dir' => 'ltr'])
                                ->rules([fn (): Closure => self::address(...)]),
                        ])
                        ->columns(3)
                        ->reorderableWithDragAndDrop()
                        ->collapsible()
                        ->cloneable()
                        ->maxItems(self::LIMIT)
                        ->defaultItems(0)
                        ->addActionLabel('افزودن شبکهٔ اجتماعی')
                        ->itemLabel(fn (array $state): ?HtmlString => self::caption($state)),
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
     * Writes the links, trimmed and in their order, to the current settings row.
     */
    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $settings = Setting::current();

        abort_if($settings === null, 404);

        $links = array_map(fn (array $row): array => [
            'name' => trim((string) ($row['name'] ?? '')),
            'icon' => (string) ($row['icon'] ?? ''),
            'url' => trim((string) ($row['url'] ?? '')),
        ], array_values((array) ($state['socials'] ?? [])));

        $settings->update(['socials' => $links]);

        Notification::make()->title('ذخیره شد')->success()->send();
    }

    /**
     * The repeater row heading: the icon and the name.
     *
     * @param  array<string, mixed>  $state
     */
    private static function caption(array $state): ?HtmlString
    {
        $name = trim((string) ($state['name'] ?? ''));
        $icon = is_string($state['icon'] ?? null) ? Icons::markup($state['icon'], 'dp-icon-svg') : null;

        if ($name === '' && $icon === null) {
            return null;
        }

        return new HtmlString('<span class="dp-icon-option">'.($icon ?? '').'<span>'.e($name).'</span></span>');
    }

    /**
     * Accepts an http or https address, or a mailto: or tel: link.
     */
    private static function address(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);

        $web = filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
        $mail = preg_match('/^mailto:[^@\s]+@[^@\s]+\.[^@\s]+$/i', $value) === 1;
        $phone = preg_match('/^tel:\+?[0-9][0-9\-\s]{3,19}$/i', $value) === 1;

        if (! $web && ! $mail && ! $phone) {
            $fail('یک نشانی درست بنویسید، مثل https://instagram.com/damoon یا mailto: و tel:.');
        }
    }
}
