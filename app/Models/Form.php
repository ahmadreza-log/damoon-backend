<?php

namespace App\Models;

use App\Support\Fields;
use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A form (فرم) built in the panel, which the site draws and visitors fill in.
 *
 * fields holds the builder blocks; definition turns them into the list the API sends.
 * button is the submit button text, and message is shown after sending, falling back to
 * the forms settings. recipients are extra emails told about each new message, on top of
 * the settings list. A form that is not active is hidden from the API and refuses messages.
 * The slug is filled from the title when the form leaves it blank; it is the form's address
 * in the API. Deleting a form deletes its messages and their uploaded files.
 *
 * Extending:
 * - Add a column in a forms migration, Fillable, and FormResource together.
 * - A new field type belongs in App\Support\Fields.
 */
#[Fillable([
    'title',
    'slug',
    'description',
    'fields',
    'button',
    'message',
    'recipients',
    'active',
])]
class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory;

    /** The submit button text when the form sets none. */
    public const BUTTON = 'ارسال';

    /**
     * Fills the slug, and deletes messages one by one so their files go with them.
     *
     * Eloquent owns this method name.
     */
    protected static function booted(): void
    {
        static::saving(function (Form $form): void {
            $form->place();
        });

        static::deleting(function (Form $form): void {
            $form->entries()->get()->each(fn (Entry $entry) => $entry->delete());
        });
    }

    /**
     * Messages sent through this form.
     *
     * @return HasMany<Entry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * Only forms that take messages.
     *
     * @param  Builder<Form>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * The fields as the flat list the API sends and submissions are checked against.
     *
     * @return list<array{key: string|null, type: string, label: string, placeholder: string|null, help: string|null, required: bool, width: string, options: list<string>, multiple: bool, min: int|float|null, max: int|float|null, accept: list<string>, size: int|null, condition: array{field: string, value: string}|null, content: string|null}>
     */
    public function definition(): array
    {
        return Fields::definition($this->fields);
    }

    /**
     * The submit button text.
     */
    public function label(): string
    {
        return filled($this->button) ? (string) $this->button : self::BUTTON;
    }

    /**
     * The text shown after a message is sent.
     */
    public function thanks(): string
    {
        return filled($this->message) ? (string) $this->message : FormSetting::current()->thanks();
    }

    /**
     * Who hears about a new message: this form's emails, and the settings list when notices are on.
     *
     * @return list<string>
     */
    public function addresses(): array
    {
        $settings = FormSetting::current();
        $list = [...(array) $this->recipients, ...($settings->notify ? $settings->addresses() : [])];

        return array_values(array_unique(array_filter($list, fn (mixed $email): bool => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
    }

    /**
     * fields and recipients are JSON lists; active is a switch.
     *
     * Eloquent owns this method name.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'recipients' => 'array',
            'active' => 'boolean',
        ];
    }

    /**
     * Stores a unique slug, using the title when the field is blank.
     */
    private function place(): void
    {
        $source = Article::link((string) ($this->slug !== null && $this->slug !== '' ? $this->slug : $this->title));
        $base = $source !== '' ? $source : 'form';
        $slug = $base;
        $count = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
                ->exists()
        ) {
            $slug = $base.'-'.$count;
            $count++;
        }

        $this->slug = $slug;
    }
}
