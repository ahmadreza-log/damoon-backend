<?php

namespace App\Filament\Resources\Forms\Pages;

use App\Filament\Resources\Forms\FormResource;
use App\Filament\Schemas\FormFields;
use App\Models\Form as Record;
use App\Models\FormSetting;
use App\Support\Fields;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;

/**
 * The form builder (فرم‌ساز) of one form: a full-screen drag and drop editor, like the page builder.
 *
 * The editor is resources/js/formbuilder.js, loaded as an Alpine component; it wears the page
 * builder's top bar and side panel. Field types are dragged from the side panel onto a live
 * preview of the form, edited in the settings tab, reordered by dragging, and tried out in
 * preview mode with their conditions working. save stores the fields in the same Builder
 * block shape the edit page uses, after the same checks (FormFields::problems), and answers
 * with the position and message of each problem so the editor can point at the field.
 * definition shows the fields as the API will send them. Only staff who may edit the form
 * can open it or save.
 *
 * Extending:
 * - A new field type needs a card and settings in resources/views/filament/pages/form-design.blade.php
 *   and defaults in resources/js/formbuilder.js. Rebuild with npm run formbuilder.
 * - Filament owns mount, getTitle, and getBreadcrumb.
 */
class DesignForm extends Page
{
    use InteractsWithRecord;

    /** The resource this page belongs to. */
    protected static string $resource = FormResource::class;

    /** The Blade view holding the editor. */
    protected string $view = 'filament.pages.form-design';

    /** The editor uses the whole width of the panel. */
    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * Loads the form and refuses staff who may not edit it.
     */
    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(FormResource::canEdit($this->getRecord()), 403);
    }

    /**
     * The heading: فرم‌ساز and the form title.
     */
    public function getTitle(): string
    {
        return 'فرم‌ساز: '.$this->getRecord()->getAttribute('title');
    }

    /**
     * The last breadcrumb.
     */
    public function getBreadcrumb(): string
    {
        return 'فرم‌ساز';
    }

    /**
     * Stores the fields the editor sent, or reports what keeps them from being saved.
     *
     * @param  array<int|string, mixed>  $fields  Builder blocks: a type and a data array each.
     * @return array{errors: array<int, string>} Problems keyed by the position of the field at fault.
     */
    public function save(array $fields): array
    {
        /** @var Record $form */
        $form = $this->getRecord();

        abort_unless(FormResource::canEdit($form), 403);

        $blocks = array_values($fields);
        $problems = FormFields::problems($blocks);

        if ($problems !== []) {
            Notification::make()->title('فرم ذخیره نشد.')->body((string) reset($problems))->danger()->send();

            return ['errors' => $problems];
        }

        $form->update(['fields' => array_map(fn (array $block): array => FormFields::clean($block), $blocks)]);

        Notification::make()->title('فرم‌ساز ذخیره شد.')->success()->send();

        return ['errors' => []];
    }

    /**
     * The fields as GET /v1/forms/{slug} will send them, for the editor's code window.
     *
     * @param  array<int|string, mixed>  $fields
     * @return list<array<string, mixed>>
     */
    public function definition(array $fields): array
    {
        abort_unless(FormResource::canEdit($this->getRecord()), 403);

        return Fields::definition(array_values($fields));
    }

    /**
     * What the editor needs besides the fields: type names, file kinds, and limits.
     *
     * @return array<string, mixed>
     */
    public function blueprint(): array
    {
        /** @var Record $form */
        $form = $this->getRecord();

        return [
            'types' => Fields::TYPES,
            'choices' => Fields::CHOICES,
            'kinds' => Fields::KINDS,
            'extensions' => Fields::EXTENSIONS,
            'pattern' => trim(Fields::PATTERN, '/'),
            'limit' => Fields::LIMIT,
            'most' => Fields::MOST,
            'short' => Fields::SHORT,
            'long' => Fields::LONG,
            'ceiling' => FormSetting::current()->size,
            'button' => $form->label(),
            'thanks' => $form->thanks(),
        ];
    }
}
