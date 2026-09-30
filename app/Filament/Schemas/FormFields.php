<?php

namespace App\Filament\Schemas;

use App\Models\FormSetting;
use App\Support\Fields;
use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The form builder field on the form edit page: one Builder block per field type.
 *
 * Every answer block has a label, a key (the input name the site sends and the answer key
 * in the API), a placeholder, help text, a required switch, a width, and an optional
 * condition that shows it only when another field's answer equals a value. Each type adds
 * its own settings: a length or value range, options, file kinds and size. The explanatory
 * text block only shows a title and text. Keys must be unique in a form, a minimum may not
 * exceed its maximum, and a condition must point at another key of the same form with a
 * value that field can have. A form holds at most Fields::MOST fields.
 *
 * problems and clean are shared with the full-screen form builder (DesignForm), so a
 * form saved from either place passes the same checks and keeps the same shape.
 *
 * Extending:
 * - A new field type is a block here, rules in rules(), a type in App\Support\Fields, and a
 *   card and settings in the form builder view, all with the same name.
 */
class FormFields
{
    /** Icons of the field types, in the Builder picker and the form builder. */
    public const ICONS = [
        'text' => Heroicon::OutlinedMinus,
        'textarea' => Heroicon::OutlinedBars3BottomLeft,
        'email' => Heroicon::OutlinedAtSymbol,
        'phone' => Heroicon::OutlinedPhone,
        'number' => Heroicon::OutlinedHashtag,
        'url' => Heroicon::OutlinedLink,
        'date' => Heroicon::OutlinedCalendar,
        'select' => Heroicon::OutlinedChevronUpDown,
        'radio' => Heroicon::OutlinedStopCircle,
        'checkboxes' => Heroicon::OutlinedListBullet,
        'checkbox' => Heroicon::OutlinedCheckCircle,
        'file' => Heroicon::OutlinedPaperClip,
        'paragraph' => Heroicon::OutlinedDocumentText,
    ];

    /** Persian messages for a block's settings. */
    private const MESSAGES = [
        'required' => '«:attribute» الزامی است.',
        'required_with' => '«:attribute» برای شرط نمایش الزامی است.',
        'string' => '«:attribute» باید متن باشد.',
        'integer' => '«:attribute» باید عدد صحیح باشد.',
        'numeric' => '«:attribute» باید عدد باشد.',
        'boolean' => '«:attribute» معتبر نیست.',
        'array' => '«:attribute» معتبر نیست.',
        'in' => '«:attribute» معتبر نیست.',
        'regex' => '«:attribute» فقط حروف کوچک انگلیسی، عدد و _ می‌پذیرد و با حرف شروع می‌شود.',
        'distinct' => 'در «:attribute» گزینهٔ تکراری هست.',
        'min' => [
            'numeric' => '«:attribute» نباید کمتر از :min باشد.',
            'array' => '«:attribute» دست‌کم :min مورد می‌خواهد.',
        ],
        'max' => [
            'string' => '«:attribute» نباید بیشتر از :max نویسه باشد.',
            'numeric' => '«:attribute» نباید بیشتر از :max باشد.',
            'array' => '«:attribute» نباید بیشتر از :max مورد داشته باشد.',
        ],
    ];

    /** Persian names of a block's settings, for the messages. */
    private const NAMES = [
        'label' => 'برچسب',
        'key' => 'کلید',
        'placeholder' => 'متن نمونه',
        'help' => 'راهنما',
        'required' => 'الزامی',
        'width' => 'عرض',
        'when' => 'کلید فیلد شرط',
        'equals' => 'مقدار شرط',
        'min' => 'کمترین',
        'max' => 'بیشترین',
        'options' => 'گزینه‌ها',
        'options.*' => 'گزینه‌ها',
        'multiple' => 'انتخاب چند گزینه',
        'accept' => 'نوع فایل‌ها',
        'accept.*' => 'نوع فایل‌ها',
        'size' => 'بیشترین حجم',
        'content' => 'متن',
    ];

    /**
     * The builder field, saved into the form's fields column.
     */
    public static function make(): Builder
    {
        return Builder::make('fields')
            ->label('فیلدها')
            ->blocks(array_map(fn (string $type): Block => self::block($type), array_keys(Fields::TYPES)))
            ->addActionLabel('افزودن فیلد')
            ->maxItems(Fields::MOST)
            ->blockPickerColumns(['default' => 2, 'md' => 3])
            ->blockNumbers(false)
            ->collapsible()
            ->cloneable()
            ->reorderableWithButtons()
            ->rules([fn (): Closure => self::check(...)])
            ->columnSpanFull();
    }

    /**
     * What keeps the blocks from being saved, keyed by the position of the block at fault; empty when they can be saved.
     *
     * Each block's settings are checked first: its type, label, key, ranges, options, and
     * file settings. When they all pass, the blocks are checked together: two fields share
     * a key; a minimum is above the maximum; or a condition points at a key the form does
     * not have, at a file field, at a checkbox with a value other than 0 or 1, or at a
     * choice field with a value that is not one of its options.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<int, string>
     */
    public static function problems(array $blocks): array
    {
        $blocks = array_values($blocks);

        if (count($blocks) > Fields::MOST) {
            return [Fields::MOST => 'یک فرم بیش از '.Fields::MOST.' فیلد نمی‌تواند داشته باشد.'];
        }

        $problems = [];

        foreach ($blocks as $index => $block) {
            $message = self::invalid($block);

            if ($message !== null) {
                $problems[$index] = $message;
            }
        }

        return $problems !== [] ? $problems : self::conflicts($blocks);
    }

    /**
     * A valid block with only the settings its type has, text trimmed, and blanks as null.
     *
     * @param  array{type: string, data?: array<string, mixed>}  $block
     * @return array{type: string, data: array<string, mixed>}
     */
    public static function clean(array $block): array
    {
        $type = $block['type'];
        $names = array_filter(array_keys(self::rules($type)), fn (string $name): bool => ! str_contains($name, '.'));
        $data = array_intersect_key(is_array($block['data'] ?? null) ? $block['data'] : [], array_flip($names));

        foreach ($data as $name => $value) {
            if (is_string($value)) {
                $data[$name] = trim($value) === '' ? null : trim($value);
            }

            if (is_array($value)) {
                $data[$name] = array_values(array_map(fn (mixed $item): mixed => is_string($item) ? trim($item) : $item, $value));
            }
        }

        return ['type' => $type, 'data' => $data];
    }

    /**
     * The rules for the settings of one block type.
     *
     * @return array<string, array<int, mixed>>
     */
    private static function rules(string $type): array
    {
        $rules = [
            'label' => [$type === 'paragraph' ? 'nullable' : 'required', 'string', 'max:255'],
            'width' => ['nullable', Rule::in(['full', 'half'])],
        ];

        if ($type === 'paragraph') {
            return $rules + ['content' => ['required', 'string', 'max:'.Fields::LONG]];
        }

        return $rules + [
            'key' => ['required', 'string', 'max:'.Fields::LIMIT, 'regex:'.Fields::PATTERN],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help' => ['nullable', 'string', 'max:255'],
            'required' => ['nullable', 'boolean'],
            'when' => ['nullable', 'string', 'max:'.Fields::LIMIT, 'regex:'.Fields::PATTERN],
            'equals' => ['nullable', 'string', 'max:255', 'required_with:when'],
        ] + match ($type) {
            'text', 'textarea' => [
                'min' => ['nullable', 'integer', 'min:0', 'max:'.Fields::LONG],
                'max' => ['nullable', 'integer', 'min:1', 'max:'.Fields::LONG],
            ],
            'number' => [
                'min' => ['nullable', 'numeric'],
                'max' => ['nullable', 'numeric'],
            ],
            'select', 'radio', 'checkboxes' => [
                'options' => ['required', 'array', 'min:1', 'max:'.Fields::MOST],
                'options.*' => ['required', 'string', 'max:255', 'distinct'],
                'multiple' => ['nullable', 'boolean'],
            ],
            'file' => [
                'accept' => ['nullable', 'array'],
                'accept.*' => [Rule::in(array_keys(Fields::KINDS))],
                'size' => ['nullable', 'integer', 'min:1', 'max:'.FormSetting::SIZES[1]],
            ],
            default => [],
        };
    }

    /**
     * The first thing wrong with one block's own settings, naming the field; null when there is none.
     */
    private static function invalid(mixed $block): ?string
    {
        $type = is_array($block) ? ($block['type'] ?? null) : null;

        if (! is_string($type) || ! array_key_exists($type, Fields::TYPES)) {
            return 'نوع فیلد معتبر نیست.';
        }

        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $validator = Validator::make($data, self::rules($type), self::MESSAGES, self::NAMES);

        if (! $validator->fails()) {
            return null;
        }

        $label = is_string($data['label'] ?? null) && trim($data['label']) !== '' ? trim($data['label']) : Fields::TYPES[$type];

        return 'فیلد «'.$label.'»: '.$validator->errors()->first();
    }

    /**
     * One block with the shared settings and the ones its type adds.
     */
    private static function block(string $type): Block
    {
        $name = Fields::TYPES[$type];

        return Block::make($type)
            ->label(fn (?array $state): string => filled($state['label'] ?? null) ? $name.': '.$state['label'] : $name)
            ->icon(self::ICONS[$type])
            ->schema($type === 'paragraph' ? self::paragraph() : [
                Grid::make(2)->schema([
                    TextInput::make('label')
                        ->label($type === 'checkbox' ? 'متن تأیید' : 'برچسب')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true),
                    TextInput::make('key')
                        ->label('کلید')
                        ->helperText('نام فیلد در API؛ حروف کوچک انگلیسی، عدد و _')
                        ->required()
                        ->maxLength(Fields::LIMIT)
                        ->regex(Fields::PATTERN)
                        ->default(fn (): string => Fields::key())
                        ->extraInputAttributes(['dir' => 'ltr']),
                    TextInput::make('placeholder')
                        ->label('متن نمونه')
                        ->maxLength(255)
                        ->visible(! in_array($type, ['checkbox', 'radio', 'checkboxes', 'file'], true)),
                    TextInput::make('help')
                        ->label('راهنما')
                        ->maxLength(255),
                    ...self::settings($type),
                    Toggle::make('required')
                        ->label('الزامی')
                        ->inline(false),
                    Select::make('width')
                        ->label('عرض')
                        ->options(['full' => 'تمام عرض', 'half' => 'نیم عرض'])
                        ->default('full')
                        ->selectablePlaceholder(false)
                        ->native(false),
                ]),
                Fieldset::make('شرط نمایش')
                    ->columns(2)
                    ->schema([
                        TextInput::make('when')
                            ->label('کلید فیلد دیگر')
                            ->helperText('خالی یعنی این فیلد همیشه نمایش داده می‌شود.')
                            ->maxLength(Fields::LIMIT)
                            ->regex(Fields::PATTERN)
                            ->extraInputAttributes(['dir' => 'ltr']),
                        TextInput::make('equals')
                            ->label('وقتی پاسخ آن برابر است با')
                            ->helperText('برای تیک تأیید، 1 یعنی تیک خورده.')
                            ->maxLength(255)
                            ->requiredWith('when'),
                    ]),
            ]);
    }

    /**
     * The settings only some types have.
     *
     * @return array<int, mixed>
     */
    private static function settings(string $type): array
    {
        return match ($type) {
            'text', 'textarea' => [
                TextInput::make('min')->label('کمترین تعداد نویسه')->integer()->minValue(0)->maxValue(Fields::LONG),
                TextInput::make('max')->label('بیشترین تعداد نویسه')->integer()->minValue(1)->maxValue(Fields::LONG)
                    ->placeholder((string) ($type === 'text' ? Fields::SHORT : Fields::LONG)),
            ],
            'number' => [
                TextInput::make('min')->label('کمترین مقدار')->numeric(),
                TextInput::make('max')->label('بیشترین مقدار')->numeric(),
            ],
            'select', 'radio', 'checkboxes' => [
                TagsInput::make('options')
                    ->label('گزینه‌ها')
                    ->placeholder('گزینه را بنویسید و Enter بزنید')
                    ->required()
                    ->rules(['max:'.Fields::MOST])
                    ->nestedRecursiveRules(['string', 'max:255'])
                    ->reorderable()
                    ->columnSpanFull(),
                ...($type === 'select' ? [Toggle::make('multiple')->label('انتخاب چند گزینه')->inline(false)] : []),
            ],
            'file' => [
                CheckboxList::make('accept')
                    ->label('نوع فایل‌های مجاز')
                    ->helperText('اگر هیچ‌کدام انتخاب نشود، همه مجازند.')
                    ->options(Fields::KINDS)
                    ->columns(3)
                    ->columnSpanFull(),
                TextInput::make('size')
                    ->label('بیشترین حجم (کیلوبایت)')
                    ->helperText('از سقف تنظیمات فرم‌ها بیشتر نمی‌شود.')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(FormSetting::SIZES[1]),
            ],
            default => [],
        };
    }

    /**
     * The explanatory text block: a title and a paragraph shown between fields.
     *
     * @return array<int, mixed>
     */
    private static function paragraph(): array
    {
        return [
            TextInput::make('label')
                ->label('عنوان')
                ->maxLength(255)
                ->live(onBlur: true),
            Textarea::make('content')
                ->label('متن')
                ->required()
                ->rows(3)
                ->maxLength(Fields::LONG),
            Select::make('width')
                ->label('عرض')
                ->options(['full' => 'تمام عرض', 'half' => 'نیم عرض'])
                ->default('full')
                ->selectablePlaceholder(false)
                ->native(false),
        ];
    }

    /**
     * Fails the Builder field with the first problem of its blocks.
     */
    private static function check(string $attribute, mixed $value, Closure $fail): void
    {
        $problems = self::problems(is_array($value) ? $value : []);

        if ($problems !== []) {
            $fail((string) reset($problems));
        }
    }

    /**
     * Problems between blocks whose own settings are valid, keyed by the position of the block at fault.
     *
     * @param  list<mixed>  $blocks
     * @return array<int, string>
     */
    private static function conflicts(array $blocks): array
    {
        $fields = [];

        foreach ($blocks as $index => $block) {
            $type = is_array($block) ? (string) ($block['type'] ?? '') : '';
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $key = trim((string) ($data['key'] ?? ''));

            if ($type === 'paragraph' || $key === '') {
                continue;
            }

            if (isset($fields[$key])) {
                return [$index => 'کلید «'.$key.'» بیش از یک بار آمده است. کلید هر فیلد باید یکتا باشد.'];
            }

            $min = $data['min'] ?? null;
            $max = $data['max'] ?? null;

            if (is_numeric($min) && is_numeric($max) && $min + 0 > $max + 0) {
                return [$index => 'در فیلد «'.$key.'» کمترین مقدار از بیشترین مقدار بزرگ‌تر است.'];
            }

            $fields[$key] = ['type' => $type, 'data' => $data, 'index' => $index];
        }

        foreach ($fields as $key => $field) {
            $when = trim((string) ($field['data']['when'] ?? ''));

            if ($when === '') {
                continue;
            }

            $target = $when !== $key ? ($fields[$when] ?? null) : null;
            $equals = trim((string) ($field['data']['equals'] ?? ''));
            $options = array_map(fn (mixed $option): string => trim((string) $option), (array) ($target['data']['options'] ?? []));

            $problem = match (true) {
                $target === null => 'به کلید «'.$when.'» اشاره می‌کند که در این فرم نیست.',
                $target['type'] === 'file' => 'نمی‌تواند به فیلد فایل وابسته باشد.',
                $target['type'] === 'checkbox' && ! in_array($equals, ['0', '1'], true) => 'برای تیک تأیید، مقدار شرط باید 0 یا 1 باشد.',
                in_array($target['type'], Fields::CHOICES, true) && ! in_array($equals, $options, true) => 'مقدار شرط باید یکی از گزینه‌های «'.$when.'» باشد.',
                default => null,
            };

            if ($problem !== null) {
                return [$field['index'] => 'شرط نمایش فیلد «'.$key.'» '.$problem];
            }
        }

        return [];
    }
}
