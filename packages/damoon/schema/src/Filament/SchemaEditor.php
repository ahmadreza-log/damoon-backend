<?php

namespace Damoon\Schema\Filament;

use Closure;
use Damoon\Schema\Contracts\Schemable;
use Damoon\Schema\Schemas;
use Damoon\Schema\Vocabulary;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * The schema editor: a repeater of schema items that any Filament form can hold.
 *
 * Drop section() into a resource form whose model implements Schemable, and a new record
 * starts with the model's blueprints, each filled with its default values. An existing record
 * without stored items shows those defaults too, and saving stores them. Each item has a type,
 * an active switch, its fields, and a live JSON-LD preview filled from the record. Outside a
 * resource, pass the types yourself, and site: true to leave out the types that need a record.
 *
 * Extending:
 * - A new field kind needs a case in input and in Vocabulary::render.
 */
class SchemaEditor
{
    /**
     * The editor in a collapsible section, with the placeholder list under it.
     *
     * @param  list<string>|null  $types  the starting types; null reads the form model's blueprints
     */
    public static function section(string $name = 'schemas', ?array $types = null, bool $site = false): Section
    {
        return Section::make('اسکیما (داده‌های ساختاریافته)')
            ->description('JSON-LD برای نتایج غنی گوگل. هر محتوا با اسکیماهای پیش‌فرض نوع خودش شروع می‌شود؛ می‌توانید آن‌ها را تغییر دهید، خاموش کنید یا اسکیمای تازه بیفزایید.')
            ->icon(Heroicon::OutlinedCodeBracketSquare)
            ->collapsible()
            ->columnSpanFull()
            ->schema([
                self::make($name, $types, $site),
                Section::make('متغیرها')
                    ->description('در هر فیلد می‌توانید این متغیرها را بنویسید؛ هنگام ساخت خروجی پر می‌شوند.')
                    ->collapsible()
                    ->collapsed()
                    ->compact()
                    ->schema([
                        View::make('schema::placeholders')->viewData(['placeholders' => self::placeholders($site)]),
                    ]),
            ]);
    }

    /**
     * The editor alone: a repeater of {type, active, fields} items.
     *
     * @param  list<string>|null  $types  the starting types; null reads the form model's blueprints
     */
    public static function make(string $name = 'schemas', ?array $types = null, bool $site = false): Repeater
    {
        $defaults = fn (Repeater $component): array => Schemas::defaults($types ?? self::blueprints($component->getModel()));

        return Repeater::make($name)
            ->hiddenLabel()
            ->schema(self::item($site))
            ->default($defaults)
            ->afterStateHydrated(function (Repeater $component) use ($defaults): void {
                if ($component->getRawState() === null) {
                    $component->rawState($defaults($component));
                }

                $component->hydrateItems();
            })
            ->columns(2)
            ->collapsible()
            ->collapsed()
            ->cloneable()
            ->reorderableWithDragAndDrop()
            ->defaultItems(0)
            ->addActionLabel('افزودن اسکیما')
            ->itemLabel(fn (array $state): string => self::label($state));
    }

    /**
     * The fields of a type, or a hint to pick one first.
     *
     * @return array<int, Component|Field>
     */
    public static function fields(?string $type): array
    {
        $fields = Vocabulary::types()[$type]['fields'] ?? null;

        if ($fields === null) {
            return [Text::make('اول نوع اسکیما را انتخاب کنید؛ فیلدهای آن با مقادیر پیش‌فرض پر می‌شوند.')->color('gray')];
        }

        $loose = [];
        $groups = [];

        foreach ($fields as $path => $field) {
            $head = explode('.', $path)[0];
            $input = self::input($path, $field);

            if ($head !== $path && isset(Vocabulary::GROUPS[$head])) {
                $groups[$head][] = $input;
            } else {
                $loose[] = $input;
            }
        }

        foreach ($groups as $head => $inputs) {
            $loose[] = Fieldset::make(Vocabulary::GROUPS[$head])
                ->schema($inputs)
                ->columns(2)
                ->columnSpanFull();
        }

        return $loose;
    }

    /**
     * The preview data: the item filled for the record, or for the site without one, as pretty JSON.
     *
     * @return array{ready: bool, json: string|null, record: bool}
     */
    public static function preview(mixed $type, mixed $fields, mixed $record): array
    {
        if (! is_string($type) || ! isset(Vocabulary::types()[$type])) {
            return ['ready' => false, 'json' => null, 'record' => false];
        }

        $owner = $record instanceof Schemable ? $record : null;
        $document = Vocabulary::render($type, is_array($fields) ? $fields : [], $owner);

        return [
            'ready' => true,
            'json' => $document !== null ? (string) json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'record' => $owner !== null && $owner instanceof Model && $owner->exists,
        ];
    }

    /**
     * The inputs of one item.
     *
     * @return array<int, Component|Field>
     */
    private static function item(bool $site): array
    {
        return [
            Select::make('type')
                ->label('نوع')
                ->options(Vocabulary::options($site))
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('fields', Vocabulary::defaults($state)))
                ->helperText(fn (Get $get): ?string => Vocabulary::types()[$get('type')]['about'] ?? null),
            Toggle::make('active')
                ->label('فعال')
                ->helperText('اسکیمای خاموش به سایت فرستاده نمی‌شود.')
                ->default(true)
                ->live()
                ->inline(false),
            Group::make(fn (Get $get): array => self::fields($get('type')))
                ->statePath('fields')
                ->columns(2)
                ->columnSpanFull(),
            View::make('schema::preview')
                ->viewData(fn (Get $get, mixed $record): array => self::preview($get('type'), $get('fields'), $record))
                ->columnSpanFull(),
        ];
    }

    /**
     * The repeater row heading: the type's Persian name, marked when switched off.
     *
     * @param  array<string, mixed>  $state
     */
    private static function label(array $state): string
    {
        $type = is_string($state['type'] ?? null) ? $state['type'] : '';
        $label = Vocabulary::types()[$type]['label'] ?? 'اسکیمای تازه';

        if ($type !== '' && $type !== 'Custom') {
            $label .= ' ('.$type.')';
        }

        return ($state['active'] ?? true) ? $label : $label.' — خاموش';
    }

    /**
     * The blueprints of a Schemable model class, or none.
     *
     * @return list<string>
     */
    private static function blueprints(?string $model): array
    {
        return $model !== null && is_subclass_of($model, Schemable::class) ? $model::blueprints() : [];
    }

    /**
     * The placeholders the editor explains; the site leaves out the record ones.
     *
     * @return array<string, array{label: string, record: bool}>
     */
    private static function placeholders(bool $site): array
    {
        return array_filter(Vocabulary::PLACEHOLDERS, fn (array $placeholder): bool => ! $site || ! $placeholder['record']);
    }

    /**
     * One field drawn by its kind.
     *
     * @param  array<string, mixed>  $field
     */
    private static function input(string $path, array $field): Field
    {
        $input = match ($field['kind'] ?? 'text') {
            'textarea' => Textarea::make($path)->rows(2)->maxLength(5000)->columnSpanFull()->live(onBlur: true),
            'list' => TagsInput::make($path)->placeholder('بنویسید و Enter بزنید')->columnSpanFull()->live(),
            'select' => Select::make($path)->options($field['options'] ?? [])->live(),
            'toggle' => Toggle::make($path)->columnSpanFull()->live(),
            'faq' => Repeater::make($path)
                ->schema([
                    TextInput::make('question')->label('پرسش')->maxLength(500)->live(onBlur: true),
                    Textarea::make('answer')->label('پاسخ')->rows(2)->maxLength(5000)->live(onBlur: true),
                ])
                ->addActionLabel('افزودن پرسش')
                ->defaultItems(0)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => is_string($state['question'] ?? null) ? $state['question'] : null)
                ->columnSpanFull()
                ->live(),
            'code' => CodeEditor::make($path)
                ->language(Language::Json)
                ->required()
                ->rules([fn (): Closure => self::json(...)])
                ->columnSpanFull()
                ->live(onBlur: true),
            default => TextInput::make($path)->maxLength(2000)->live(onBlur: true),
        };

        return $input
            ->label($field['label'])
            ->helperText($field['help'] ?? null);
    }

    /**
     * Rejects text that is not a JSON object or a list of them.
     */
    private static function json(string $attribute, mixed $value, Closure $fail): void
    {
        $data = is_string($value) ? json_decode($value, true) : null;

        if (! is_array($data) || $data === []) {
            $fail('یک JSON درست بنویسید: یک شیء با @type، یا فهرستی از آن‌ها.');
        }
    }
}
