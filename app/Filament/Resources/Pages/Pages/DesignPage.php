<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Models\Page as Record;
use App\Support\Library;
use App\Support\Sizes;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The page builder (صفحه‌ساز) of one page: a full-screen drag and drop editor like Elementor.
 *
 * The editor is GrapesJS, loaded from resources/js/designer.js as an Alpine component. It
 * covers the whole window with its own top bar, holding save and the way back to the page form.
 * It opens with the saved design, or with the saved HTML when there is no design yet.
 * save stores the design JSON, the HTML, and the CSS on the page. Pictures are offered
 * from the media library, and store puts a new upload into Page::DESIGNS with its sizes.
 * Only staff who may edit the page can open it or save.
 *
 * Extending:
 * - Blocks live in resources/js/designer.js, the top bar and side panel in the Blade view. Rebuild with npm run designer.
 * - Filament owns mount, getTitle, and getBreadcrumb.
 */
class DesignPage extends Page
{
    use InteractsWithRecord;
    use WithFileUploads;

    /** The largest HTML or CSS one save may send, in characters. */
    public const LIMIT = 2000000;

    /** The resource this page belongs to. */
    protected static string $resource = PageResource::class;

    /** The Blade view holding the editor. */
    protected string $view = 'filament.pages.design';

    /** The editor uses the whole width of the panel. */
    protected Width|string|null $maxContentWidth = Width::Full;

    /** A picture the editor is uploading, before store moves it into the media library. */
    public ?TemporaryUploadedFile $upload = null;

    /**
     * Loads the page and refuses staff who may not edit it.
     */
    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(PageResource::canEdit($this->getRecord()), 403);
    }

    /**
     * The heading: صفحه‌ساز and the page title.
     */
    public function getTitle(): string
    {
        return 'صفحه‌ساز: '.$this->getRecord()->getAttribute('title');
    }

    /**
     * The last breadcrumb.
     */
    public function getBreadcrumb(): string
    {
        return 'صفحه‌ساز';
    }

    /**
     * Stores the design, HTML, and CSS the editor sent.
     *
     * @param  array<string, mixed>  $project
     */
    public function save(array $project, string $html, string $css): void
    {
        /** @var Record $page */
        $page = $this->getRecord();

        abort_unless(PageResource::canEdit($page), 403);

        Validator::make(['html' => $html, 'css' => $css], [
            'html' => ['string', 'max:'.self::LIMIT],
            'css' => ['string', 'max:'.self::LIMIT],
        ], [
            'max' => 'صفحه‌ساز بیش از اندازه بزرگ است.',
        ])->validate();

        $page->update([
            'design' => Record::project($project),
            'markup' => trim($html) === '' ? null : trim($html),
            'style' => trim($css) === '' ? null : trim($css),
        ]);

        Notification::make()->title('صفحه‌ساز ذخیره شد.')->success()->send();
    }

    /**
     * Moves the uploaded picture into the media library and returns it for the editor's picture list.
     *
     * @return array{src: string, name: string, type: string}|null
     */
    public function store(): ?array
    {
        abort_unless(PageResource::canEdit($this->getRecord()), 403);

        $this->validate([
            'upload' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ], [
            'upload.image' => 'فقط تصویر می‌توان بارگذاری کرد.',
            'upload.mimes' => 'قالب تصویر باید jpg، png، webp یا gif باشد.',
            'upload.max' => 'حجم تصویر نباید بیش از ۱۰ مگابایت باشد.',
        ]);

        $file = $this->upload;

        if (! $file instanceof TemporaryUploadedFile) {
            return null;
        }

        $path = $file->storeAs(Record::DESIGNS, Str::ulid().'.'.strtolower($file->getClientOriginalExtension()), 'public');
        $this->upload = null;

        if (! is_string($path)) {
            return null;
        }

        Sizes::make($path);

        return ['src' => Library::url($path), 'name' => basename($path), 'type' => 'image'];
    }

    /**
     * Media library pictures for the editor's picture list, newest first.
     *
     * @return array<int, array{src: string, name: string, type: string}>
     */
    public function assets(): array
    {
        return array_map(fn (array $row): array => [
            'src' => Library::url($row['path']),
            'name' => $row['title'] !== '' ? $row['title'] : $row['name'],
            'type' => 'image',
        ], Library::pictures());
    }
}
