<?php

namespace App\Filament\Pages;

use App\Auth\Section;
use App\Models\Setting;
use App\Models\User;
use App\Support\Frontend;
use Closure;
use Damoon\Schema\Filament\SchemaEditor;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * The general settings page (تنظیمات عمومی), first in the settings group.
 *
 * It edits the site title and description chosen at install, the public website's address and
 * the address pattern of each content type, and the site-wide schemas. The title is the panel
 * brand and the site name in SEO previews, so saving clears Setting's cached brand. The website is
 * not served by this app, so every content address in the API, the SEO tags, and the schemas is
 * built from the address and patterns here (App\Support\Frontend). The site-wide schemas start as
 * Organization and WebSite; they go into every record's schemas and to /v1/schema. installed_at is
 * never touched here. It needs the settings permission.
 *
 * Extending:
 * - A new site-wide setting is a field here, a column on settings, and Setting's fillable list.
 * - A new content type's pattern field comes from Frontend::ROUTES.
 * - Filament owns form, content, mount, and canAccess.
 *
 * @property-read Schema $form
 */
class SiteSettings extends Page
{
    /** The item name in the side menu. */
    protected static ?string $navigationLabel = 'تنظیمات عمومی';

    /** The page heading and browser tab title. */
    protected static ?string $title = 'تنظیمات عمومی';

    /** The menu group this item sits in. */
    protected static string|\UnitEnum|null $navigationGroup = 'تنظیمات';

    /** The icon next to the menu item. */
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    /** The position inside the settings group: first. */
    protected static ?int $navigationSort = 1;

    /** The URL after /admin. */
    protected static ?string $slug = 'settings';

    /**
     * The form state.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Shows the page only for accounts with the settings section.
     *
     * Filament owns this method name.
     */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can(Section::SETTINGS);
    }

    /**
     * Fills the form with the saved settings; empty patterns show their defaults.
     *
     * Livewire owns this method name.
     */
    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $settings = Setting::current();
        $routes = [];

        foreach (array_keys(Frontend::types()) as $key) {
            $routes[$key] = $settings?->routes[$key] ?? null;
        }

        $this->form->fill([
            'title' => $settings?->title,
            'description' => $settings?->description,
            'url' => $settings?->url,
            'routes' => $routes,
            'schemas' => $settings?->schemas,
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
            FormSection::make('مشخصات سایت')
                ->description('عنوان سایت نام پنل و نام سایت در پیش‌نمایش‌های سئو هم هست.')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->schema([
                    TextInput::make('title')
                        ->label('عنوان سایت')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('توضیح کوتاه')
                        ->helperText('یکی دو جمله دربارهٔ سایت.')
                        ->required()
                        ->rows(3)
                        ->maxLength(1000),
                ]),
            FormSection::make('نشانی سایت')
                ->description('سایت جدا از این پنل اجرا می‌شود؛ نشانی هر محتوا در API، تگ‌های سئو و اسکیماها از این بخش ساخته می‌شود.')
                ->icon(Heroicon::OutlinedLink)
                ->columns(2)
                ->schema([
                    TextInput::make('url')
                        ->label('نشانی سایت')
                        ->placeholder('https://damoon.ir')
                        ->helperText(fn (): HtmlString => new HtmlString('بدون / در پایان. اگر خالی بماند، نشانی همین برنامه (<bdi dir="ltr">'.e((string) config('app.url')).'</bdi>) به کار می‌رود.'))
                        ->url()
                        ->maxLength(255)
                        ->extraInputAttributes(['dir' => 'ltr'])
                        ->columnSpanFull(),
                    ...$this->patterns(),
                ]),
            SchemaEditor::section('schemas', Setting::SCHEMAS, site: true)
                ->heading('اسکیمای کل سایت')
                ->description(new HtmlString('این اسکیماها در خروجی همهٔ محتواها و در نشانی <bdi dir="ltr">/v1/schema</bdi> برای صفحهٔ اصلی و فهرست‌ها می‌آیند. اسکیماهای هر محتوا در فرم خود آن محتواست.')),
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
     * Writes the settings to the current settings row.
     *
     * Install always creates that row, and EnsureInstalled keeps the panel closed until it exists.
     * A pattern left empty, or the same as its default, is not stored, so a changed default reaches it.
     */
    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $settings = Setting::current();

        abort_if($settings === null, 404);

        $routes = [];

        foreach (Frontend::types() as $key => $type) {
            $pattern = '/'.trim((string) ($state['routes'][$key] ?? ''), " /\t\n");

            if ($pattern !== '/' && $pattern !== $type['default']) {
                $routes[$key] = $pattern;
            }
        }

        $url = rtrim(trim((string) ($state['url'] ?? '')), '/');

        $settings->update([
            'title' => trim((string) $state['title']),
            'description' => trim((string) $state['description']),
            'url' => $url !== '' ? $url : null,
            'routes' => $routes !== [] ? $routes : null,
            'schemas' => array_values((array) ($state['schemas'] ?? [])),
        ]);

        Notification::make()->title('ذخیره شد')->success()->send();
    }

    /**
     * One address pattern field per content type.
     *
     * @return list<TextInput>
     */
    private function patterns(): array
    {
        $fields = [];

        foreach (Frontend::types() as $key => $type) {
            $fields[] = TextInput::make('routes.'.$key)
                ->label('نشانی '.$type['label'])
                ->placeholder($type['default'])
                ->maxLength(255)
                ->extraInputAttributes(['dir' => 'ltr'])
                ->rules([fn (): Closure => self::pattern(...)]);
        }

        $fields[0] = $fields[0]->helperText(new HtmlString('مسیر هر صفحه با <bdi dir="ltr">{slug}</bdi> به جای نامک، مثل <bdi dir="ltr">/blog/{slug}</bdi>. خالی یعنی پیش‌فرض.'));

        return $fields;
    }

    /**
     * Accepts an empty value or a path with {slug} and no spaces.
     */
    private static function pattern(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        if (! str_contains($value, '{slug}') || preg_match('~\s|\?|#~', $value) === 1) {
            $fail('مسیر باید {slug} داشته باشد و فاصله، ? یا # نداشته باشد؛ مثل /blog/{slug}.');
        }
    }
}
