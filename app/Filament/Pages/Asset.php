<?php

namespace App\Filament\Pages;

use App\Models\Asset as AssetRecord;
use App\Support\Library;
use App\Support\Shamsi;
use App\Support\Sizes;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Morilog\Jalali\Jalalian;

/**
 * The detail page for one public file.
 *
 * Title, alt text, image title, and description are saved on the asset row.
 * The information box reads the file itself: name, type, size, pixels, and date.
 * The sizes box lists the WebP copies from Sizes. The header button rebuilds them.
 *
 * Extending:
 * - Filament owns form, content, getHeaderActions, mount, and canAccess.
 * - A new text field belongs on the asset model and in form() together.
 *
 * @property-read Schema $form
 */
class Asset extends Page
{
    protected static ?string $slug = 'media/{asset}';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = 'محتوا';

    /**
     * @var array<string, mixed>
     */
    public array $facts = [];

    public string $asset = '';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Shows the page only for accounts that may open the media library.
     *
     * Filament owns this method name.
     */
    public static function canAccess(): bool
    {
        return Media::canAccess();
    }

    /**
     * Loads the file and the words already saved for it.
     *
     * Livewire owns this method name.
     */
    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $row = Library::find($this->asset);
        abort_unless(is_array($row), 404);

        $this->facts = $row;
        $stored = AssetRecord::hold((string) $row['path']);
        $this->heading = filled($stored->title) ? (string) $stored->title : (string) $row['name'];

        $this->form->fill([
            'title' => $stored->title,
            'alt' => $stored->alt,
            'caption' => $stored->caption,
            'description' => $stored->description,
        ]);
    }

    /**
     * The page heading is the saved title, or the file name.
     *
     * Filament owns this method name.
     */
    public function getTitle(): string|Htmlable
    {
        return $this->heading ?? 'رسانه';
    }

    /**
     * @return array<string|Htmlable>
     */
    public function getBreadcrumbs(): array
    {
        return [
            Media::getUrl() => 'رسانه‌ها',
            $this->getTitle(),
        ];
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
     * Editable words for the file.
     *
     * Filament owns this method name.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('عنوان')->maxLength(255),
            TextInput::make('alt')->label('متن جایگزین')->maxLength(255),
            TextInput::make('caption')->label('عنوان تصویر')->maxLength(255),
            Textarea::make('description')->label('توضیحات')->rows(4)->maxLength(5000),
        ]);
    }

    /**
     * Preview, the form, and the image information box.
     *
     * Filament owns this method name.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Html::make(fn (): HtmlString => $this->picture()),
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                Form::make([EmbeddedSchema::make('form')])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')->label('ذخیره')->submit('save'),
                            Action::make('back')->label('بازگشت')->url(Media::getUrl())->color('gray'),
                        ]),
                    ])
                    ->columnSpan(['lg' => 2]),
                Section::make('اطلاعات تصویر')
                    ->schema([
                        TextEntry::make('name')->label('نام فایل')->state(fn (): string => (string) ($this->facts['name'] ?? '')),
                        TextEntry::make('kind')->label('نوع')->state(fn (): string => $this->kind((string) ($this->facts['mime'] ?? ''))),
                        TextEntry::make('weight')->label('حجم')->state(fn (): string => Library::weight((int) ($this->facts['size'] ?? 0))),
                        TextEntry::make('pixels')->label('ابعاد')->state(fn (): string => $this->pixels()),
                        TextEntry::make('when')->label('تاریخ')->state(fn (): string => $this->when((string) ($this->facts['modified'] ?? ''))),
                        TextEntry::make('place')->label('محل')->state(fn (): string => (string) ($this->facts['place'] ?? '')),
                        TextEntry::make('usage')->label('کاربرد')->state(fn (): string => (string) ($this->facts['usage'] ?? '')),
                        TextEntry::make('url')
                            ->label('نشانی')
                            ->state(fn (): string => (string) ($this->facts['url'] ?? ''))
                            ->url(fn (): string => (string) ($this->facts['url'] ?? ''))
                            ->openUrlInNewTab(),
                    ])
                    ->columnSpan(['lg' => 1]),
            ]),
            Section::make('اندازه‌ها')
                ->description('نسخه‌های WebP این تصویر برای جاهای مختلف سایت.')
                ->visible(fn (): bool => Sizes::fits((string) ($this->facts['path'] ?? '')))
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                ->schema(fn (): array => $this->sizes()),
        ]);
    }

    /**
     * Rebuilds the sizes, for images uploaded before sizes existed or after the list changed.
     *
     * Filament owns this method name.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('resize')
                ->label('ساخت اندازه‌ها')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => Sizes::fits((string) ($this->facts['path'] ?? '')))
                ->action(function (): void {
                    $made = Sizes::make((string) $this->facts['path']);
                    $this->facts = Library::find($this->asset) ?? $this->facts;

                    $made === []
                        ? Notification::make()->title('ساخت اندازه‌ها انجام نشد')->danger()->send()
                        : Notification::make()->title('اندازه‌ها ساخته شد')->success()->send();
                }),
        ];
    }

    /**
     * One entry per built size, or a note when none exist yet.
     *
     * @return array<int, TextEntry>
     */
    private function sizes(): array
    {
        $sizes = (array) ($this->facts['sizes'] ?? []);

        if ($sizes === []) {
            return [
                TextEntry::make('nosizes')
                    ->hiddenLabel()
                    ->state('هنوز اندازه‌ای ساخته نشده است.')
                    ->columnSpanFull(),
            ];
        }

        $entries = [];

        foreach ($sizes as $name => $size) {
            $pixels = is_int($size['width']) && is_int($size['height']) ? $size['width'].' × '.$size['height'] : '—';

            $entries[] = TextEntry::make('size_'.$name)
                ->label($size['label'])
                ->state($pixels.' — '.Library::weight((int) $size['bytes']))
                ->url($size['url'])
                ->openUrlInNewTab();
        }

        return $entries;
    }

    /**
     * Writes the form onto the asset row for this file.
     */
    public function save(): void
    {
        $row = Library::find($this->asset);
        abort_unless(is_array($row), 404);

        $state = $this->form->getState();
        $title = $this->clear($state['title'] ?? null);

        AssetRecord::query()->updateOrCreate(
            ['path' => $row['path']],
            [
                'title' => $title,
                'alt' => $this->clear($state['alt'] ?? null),
                'caption' => $this->clear($state['caption'] ?? null),
                'description' => $this->clear($state['description'] ?? null),
            ],
        );

        $this->facts = $row;
        $this->heading = $title ?? (string) $row['name'];

        Notification::make()->title('ذخیره شد')->success()->send();
    }

    /**
     * The preview shown above the form.
     */
    private function picture(): HtmlString
    {
        $preview = $this->facts['preview'] ?? null;

        if (! is_string($preview) || $preview === '') {
            return new HtmlString('<p>فایل</p>');
        }

        $url = e((string) ($this->facts['url'] ?? ''));
        $alt = e((string) ($this->data['alt'] ?? $this->facts['name'] ?? ''));

        return new HtmlString('<img src="'.$url.'" alt="'.$alt.'" style="max-height:16rem;width:auto;border-radius:0.75rem">');
    }

    /**
     * A Persian file kind, with the mime type beside it.
     */
    private function kind(string $mime): string
    {
        $label = match (true) {
            str_starts_with($mime, 'image/') => 'تصویر',
            str_starts_with($mime, 'video/') => 'ویدیو',
            $mime === 'application/pdf' => 'پی‌دی‌اف',
            default => 'فایل',
        };

        return $mime === '' ? $label : $label.' ('.$mime.')';
    }

    /**
     * Width and height, or a dash when the file has none.
     */
    private function pixels(): string
    {
        $width = $this->facts['width'] ?? null;
        $height = $this->facts['height'] ?? null;

        if (! is_int($width) || ! is_int($height) || $width < 1 || $height < 1) {
            return '—';
        }

        return $width.' × '.$height;
    }

    /**
     * The file time on the Iran clock, as a Jalali date.
     */
    private function when(string $modified): string
    {
        if ($modified === '') {
            return '—';
        }

        return Jalalian::fromCarbon(Carbon::parse($modified)->timezone(Shamsi::ZONE))->format(Shamsi::TIME);
    }

    /**
     * Blank text becomes null so the column stays empty.
     */
    private function clear(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
