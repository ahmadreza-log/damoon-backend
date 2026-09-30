<?php

namespace App\Support;

use App\Models\Form;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The field types of the form builder and everything the server does with them.
 *
 * A form stores its fields as Filament Builder blocks: a type and a data array. definition
 * turns them into the flat list the API sends, the same shape for every type, so a site can
 * draw any form from it. rules, attributes, and messages validate a submission against that
 * list, answers turns the valid input into the snapshot an entry keeps, and text prints one
 * answer for the inbox, the CSV file, and the notification email.
 *
 * A field may have a condition: it shows only when another field's answer equals a value (or
 * contains it, for a multiple choice). A hidden field is not validated and not stored.
 * Uploaded files go to the private local disk under forms/{form id}; they never become public.
 *
 * Nothing sent is trusted. prepare cleans every answer before validation: HTML tags, control
 * and invisible characters, and extra spaces go, and Arabic ي and ك become Persian ی and ک.
 * rules then accept only the shape each type allows, and anything not in the form is dropped.
 * On the way out, link, markdown, and cell keep an answer from becoming a script link, email
 * markup, or a spreadsheet formula, and inside keeps downloads within the form's folder.
 *
 * Extending:
 * - A new field type is a key in TYPES, a block in App\Filament\Schemas\FormFields, and an arm in rule, normalize, and value.
 * - A new file kind is a key in KINDS and EXTENSIONS.
 */
class Fields
{
    /** Field types with their Persian names, in the order the builder offers them. */
    public const TYPES = [
        'text' => 'متن کوتاه',
        'textarea' => 'متن بلند',
        'email' => 'ایمیل',
        'phone' => 'تلفن',
        'number' => 'عدد',
        'url' => 'پیوند',
        'date' => 'تاریخ',
        'select' => 'فهرست کشویی',
        'radio' => 'تک‌انتخابی',
        'checkboxes' => 'چندانتخابی',
        'checkbox' => 'تیک تأیید',
        'file' => 'فایل',
        'paragraph' => 'متن توضیحی',
    ];

    /** Types whose answer is picked from the field's options. */
    public const CHOICES = ['select', 'radio', 'checkboxes'];

    /** File kinds the file field may accept, with their Persian names. */
    public const KINDS = [
        'image' => 'تصویر',
        'pdf' => 'PDF',
        'document' => 'سند (Word، Excel، متن)',
        'archive' => 'فایل فشرده',
        'audio' => 'صوت',
        'video' => 'ویدیو',
    ];

    /** The extensions each file kind allows. */
    public const EXTENSIONS = [
        'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'pdf' => ['pdf'],
        'document' => ['doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'],
        'archive' => ['zip', 'rar'],
        'audio' => ['mp3', 'm4a', 'ogg', 'wav'],
        'video' => ['mp4', 'webm', 'mov'],
    ];

    /** What a field key may look like: it is the input name on the site and the answer key in the API. */
    public const PATTERN = '/^[a-z][a-z0-9_]*$/';

    /** The private disk uploaded answers are stored on. */
    public const DISK = 'local';

    /** The folder on that disk; each form gets a subfolder named after its id. */
    public const FOLDER = 'forms';

    /** The longest short text and long text answers when the field sets no maximum; LONG also caps any maximum. */
    public const SHORT = 255;

    public const LONG = 5000;

    /** The longest field key. */
    public const LIMIT = 40;

    /** The most fields a form, or options a choice field, may have. */
    public const MOST = 100;

    /** The earliest and latest date a date answer may be; a Jalali year typed as Gregorian falls outside. */
    public const EARLIEST = '1900-01-01';

    public const LATEST = '2100-12-31';

    /** A hidden input the site adds and leaves empty; bots that fill every input get their message refused. */
    public const HONEYPOT = '_gotcha';

    /** Control characters, zero-width spaces, and direction overrides; the Persian half-space (U+200C) stays. */
    private const INVISIBLE = '/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}\x{200B}\x{202A}-\x{202E}\x{2060}\x{2066}-\x{2069}\x{FEFF}]/u';

    /**
     * The builder blocks as the flat field list the API sends.
     *
     * Every field has the same keys; the ones a type does not use are null or empty.
     *
     * @return list<array{key: string|null, type: string, label: string, placeholder: string|null, help: string|null, required: bool, width: string, options: list<string>, multiple: bool, min: int|float|null, max: int|float|null, accept: list<string>, size: int|null, condition: array{field: string, value: string}|null, content: string|null}>
     */
    public static function definition(mixed $blocks): array
    {
        $fields = [];
        $keys = [];

        foreach (is_array($blocks) ? $blocks : [] as $block) {
            $type = is_array($block) ? (string) ($block['type'] ?? '') : '';
            $data = is_array($block) && is_array($block['data'] ?? null) ? $block['data'] : [];

            if (! array_key_exists($type, self::TYPES)) {
                continue;
            }

            $field = [
                'key' => $type === 'paragraph' ? null : self::clear($data['key'] ?? null),
                'type' => $type,
                'label' => (string) self::clear($data['label'] ?? null),
                'placeholder' => self::clear($data['placeholder'] ?? null),
                'help' => self::clear($data['help'] ?? null),
                'required' => $type !== 'paragraph' && (bool) ($data['required'] ?? false),
                'width' => ($data['width'] ?? 'full') === 'half' ? 'half' : 'full',
                'options' => in_array($type, self::CHOICES, true) ? self::options($data['options'] ?? []) : [],
                'multiple' => $type === 'checkboxes' || ($type === 'select' && (bool) ($data['multiple'] ?? false)),
                'min' => in_array($type, ['text', 'textarea', 'number'], true) ? self::number($data['min'] ?? null) : null,
                'max' => in_array($type, ['text', 'textarea', 'number'], true) ? self::number($data['max'] ?? null) : null,
                'accept' => $type === 'file' ? self::accept($data['accept'] ?? []) : [],
                'size' => $type === 'file' && is_numeric($data['size'] ?? null) ? max(1, (int) $data['size']) : null,
                'condition' => self::condition($data),
                'content' => $type === 'paragraph' ? self::clear($data['content'] ?? null, true) : null,
            ];

            if ($type !== 'paragraph' && (! self::named($field['key']) || in_array($field['key'], $keys, true))) {
                continue;
            }

            $keys[] = $field['key'];
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * The fields that take an answer, leaving out explanatory text.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    public static function inputs(array $fields): array
    {
        return array_values(array_filter($fields, fn (array $field): bool => $field['key'] !== null));
    }

    /**
     * Which answer fields are shown for this input, keyed by field key.
     *
     * A field whose condition points at a hidden field is hidden too.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, bool>
     */
    public static function shown(array $fields, array $input): array
    {
        $shown = [];

        foreach (self::inputs($fields) as $field) {
            $condition = $field['condition'];

            if ($condition === null) {
                $shown[$field['key']] = true;

                continue;
            }

            $other = $condition['field'];
            $answer = ($shown[$other] ?? true) ? ($input[$other] ?? null) : null;

            $shown[$field['key']] = self::matches($answer, $condition['value']);
        }

        return $shown;
    }

    /**
     * Cleans every answer before validation, so the rules see what would be stored.
     *
     * Text goes through sanitize (only a long text keeps its line breaks). An email becomes
     * lowercase; a phone loses spaces, dashes, and brackets; a number loses thousands
     * separators; phone, number, and date get Latin digits, and a date may use slashes. An
     * answer that is empty after cleaning becomes null. Values of the wrong shape are left
     * for the rules to refuse.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function prepare(array $fields, array $input): array
    {
        foreach (self::inputs($fields) as $field) {
            $key = $field['key'];
            $value = $input[$key] ?? null;

            if ($field['multiple'] && is_array($value)) {
                $items = array_map(fn (mixed $item): mixed => is_string($item) ? self::sanitize($item) : $item, $value);
                $input[$key] = array_values(array_filter($items, fn (mixed $item): bool => $item !== '' && $item !== null));

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $value = self::normalize((string) $field['type'], self::sanitize($value, $field['type'] === 'textarea'));
            $input[$key] = $value === '' ? null : $value;
        }

        return $input;
    }

    /**
     * Text without HTML tags, control or invisible characters, or extra spaces, and with Persian ی and ک.
     *
     * Bytes that are not UTF-8 are replaced. Only multiline text keeps line breaks, with at
     * most one empty line in a row.
     */
    public static function sanitize(string $value, bool $multiline = false): string
    {
        $value = mb_scrub($value, 'UTF-8');
        $value = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#isu', '', $value);
        $value = (string) preg_replace('#<\s*/?\s*[a-z!?][^>]*>#iu', '', $value);
        $value = (string) preg_replace(self::INVISIBLE, '', $value);
        $value = strtr($value, ['ي' => 'ی', 'ك' => 'ک', "\r\n" => "\n", "\r" => "\n"]);

        if (! $multiline) {
            return trim((string) preg_replace('/\s+/u', ' ', $value));
        }

        $value = (string) preg_replace('/[^\S\n]+/u', ' ', $value);
        $value = (string) preg_replace('/ *\n */u', "\n", $value);

        return trim((string) preg_replace('/\n{3,}/u', "\n\n", $value));
    }

    /**
     * The address when it is a web link with a host, or null, so a stored answer never becomes a javascript: or data: link.
     */
    public static function link(mixed $url): ?string
    {
        if (! is_string($url) || $url === '' || strlen($url) > 2048 || preg_match('/[\s<>"]/u', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && ($parts['host'] ?? '') !== ''
            ? $url
            : null;
    }

    /**
     * Text with Markdown punctuation escaped, so an answer shows as typed in the email and adds no links or formatting.
     *
     * HTML is escaped by the mail template itself.
     */
    public static function markdown(string $text): string
    {
        return (string) preg_replace('/([\\\\`*_{}\[\]()#|~!])/', '\\\\$1', $text);
    }

    /**
     * A CSV cell that a spreadsheet shows as text: a value starting with =, +, -, @, or a tab gets a leading quote.
     */
    public static function cell(string $text): string
    {
        return preg_match('/^[=+\-@\t\r]/', $text) === 1 && ! is_numeric($text) ? "'".$text : $text;
    }

    /**
     * Whether a stored path lies inside the given form's upload folder.
     */
    public static function inside(mixed $path, int|string|null $form): bool
    {
        return is_string($path)
            && $form !== null
            && str_starts_with($path, self::FOLDER.'/'.$form.'/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\');
    }

    /**
     * Laravel rules for every answer field; a hidden field is excluded.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $input
     * @param  int  $ceiling  The largest file any field may take, in kilobytes, from the forms settings.
     * @return array<string, array<int, mixed>>
     */
    public static function rules(array $fields, array $input, int $ceiling): array
    {
        $shown = self::shown($fields, $input);
        $rules = [];

        foreach (self::inputs($fields) as $field) {
            $key = $field['key'];

            if (! ($shown[$key] ?? true)) {
                $rules[$key] = ['exclude'];

                continue;
            }

            $rules[$key] = self::rule($field, $ceiling);

            if ($field['multiple']) {
                $rules[$key.'.*'] = ['string', 'distinct', Rule::in($field['options'])];
            }
        }

        return $rules;
    }

    /**
     * Field labels for the validation messages, keyed like the rules.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, string>
     */
    public static function attributes(array $fields): array
    {
        $names = [];

        foreach (self::inputs($fields) as $field) {
            $names[$field['key']] = $field['label'];
            $names[$field['key'].'.*'] = $field['label'];
        }

        return $names;
    }

    /**
     * Persian validation messages for submissions; min and max have one message per kind of value.
     *
     * @return array<string, string|array<string, string>>
     */
    public static function messages(): array
    {
        return [
            'required' => 'فیلد «:attribute» الزامی است.',
            'accepted' => '«:attribute» باید تأیید شود.',
            'string' => '«:attribute» معتبر نیست.',
            'email' => '«:attribute» ایمیل معتبری نیست.',
            'url' => '«:attribute» پیوند معتبری نیست.',
            'regex' => '«:attribute» معتبر نیست.',
            'numeric' => '«:attribute» باید عدد باشد.',
            'boolean' => '«:attribute» معتبر نیست.',
            'array' => '«:attribute» معتبر نیست.',
            'in' => 'گزینهٔ انتخاب‌شده برای «:attribute» معتبر نیست.',
            'distinct' => 'یک گزینه برای «:attribute» بیش از یک بار فرستاده شده است.',
            'date_format' => '«:attribute» باید تاریخی میلادی به شکل YYYY-MM-DD باشد.',
            'after_or_equal' => '«:attribute» نباید پیش از :date باشد.',
            'before_or_equal' => '«:attribute» نباید پس از :date باشد.',
            'file' => '«:attribute» باید فایل باشد.',
            'uploaded' => 'بارگذاری «:attribute» انجام نشد.',
            'mimes' => 'نوع فایل «:attribute» مجاز نیست. پسوندهای مجاز: :values',
            'extensions' => 'نوع فایل «:attribute» مجاز نیست. پسوندهای مجاز: :values',
            'min' => [
                'string' => '«:attribute» باید دست‌کم :min نویسه باشد.',
                'numeric' => '«:attribute» نباید کمتر از :min باشد.',
            ],
            'max' => [
                'string' => '«:attribute» نباید بیشتر از :max نویسه باشد.',
                'numeric' => '«:attribute» نباید بیشتر از :max باشد.',
                'file' => 'حجم «:attribute» نباید بیشتر از :max کیلوبایت باشد.',
                'array' => 'برای «:attribute» بیشتر از :max گزینه نمی‌شود انتخاب کرد.',
            ],
        ];
    }

    /**
     * The valid input as the answer list an entry keeps, storing uploaded files on the private disk.
     *
     * Every shown field gets a row, an empty one with null, so each entry of a form has the same rows.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @return list<array{key: string, label: string, type: string, value: mixed}>
     */
    public static function answers(array $fields, array $data, Form $form): array
    {
        $shown = self::shown($fields, $data);
        $answers = [];

        foreach (self::inputs($fields) as $field) {
            if (! ($shown[$field['key']] ?? true)) {
                continue;
            }

            $answers[] = [
                'key' => (string) $field['key'],
                'label' => (string) $field['label'],
                'type' => (string) $field['type'],
                'value' => self::value($field, $data[$field['key']] ?? null, $form),
            ];
        }

        return $answers;
    }

    /**
     * One answer as text: choices joined, a tick as بله or خیر, a file as its name, and — when empty.
     */
    public static function text(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '', $value === [] => '—',
            is_bool($value) => $value ? 'بله' : 'خیر',
            is_array($value) && isset($value['path']) => (string) ($value['name'] ?? basename((string) $value['path'])),
            is_array($value) => implode('، ', array_map(fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $value)),
            default => (string) $value,
        };
    }

    /**
     * Latin digits in place of Persian and Arabic ones.
     */
    public static function latin(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /**
     * A field key made from nothing, for a new block in the builder.
     */
    public static function key(): string
    {
        return 'field_'.Str::lower(Str::random(6));
    }

    /**
     * The rules for one shown field.
     *
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private static function rule(array $field, int $ceiling): array
    {
        $presence = $field['required'] ? 'required' : 'nullable';
        $min = $field['min'];
        $max = $field['max'];

        return match ($field['type']) {
            'text', 'textarea' => array_values(array_filter([
                $presence,
                'string',
                $min !== null ? 'min:'.max(0, (int) $min) : null,
                'max:'.min((int) ($max ?? ($field['type'] === 'text' ? self::SHORT : self::LONG)), self::LONG),
            ])),
            'email' => [$presence, 'string', 'max:255', 'email:rfc'],
            'phone' => [$presence, 'string', 'regex:/^\+?[0-9]{6,15}$/'],
            'url' => [$presence, 'string', 'max:2048', 'url:http,https'],
            'number' => array_values(array_filter([
                $presence,
                'numeric',
                'regex:/^-?[0-9]{1,15}(\.[0-9]{1,6})?$/',
                $min !== null ? 'min:'.$min : null,
                $max !== null ? 'max:'.$max : null,
            ], fn (mixed $rule): bool => $rule !== null)),
            'date' => [$presence, 'date_format:Y-m-d', 'after_or_equal:'.self::EARLIEST, 'before_or_equal:'.self::LATEST],
            'select', 'radio', 'checkboxes' => $field['multiple']
                ? [$presence, 'array', 'max:'.max(1, count($field['options']))]
                : [$presence, 'string', Rule::in($field['options'])],
            'checkbox' => $field['required'] ? ['accepted'] : ['nullable', 'boolean'],
            'file' => [
                $presence,
                'file',
                'mimes:'.implode(',', $field['accept']),
                'extensions:'.implode(',', $field['accept']),
                'max:'.min((int) ($field['size'] ?? $ceiling), $ceiling),
            ],
            default => [$presence],
        };
    }

    /**
     * One valid answer as it is stored.
     *
     * @param  array<string, mixed>  $field
     */
    private static function value(array $field, mixed $value, Form $form): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return $field['type'] === 'checkbox' ? false : null;
        }

        return match ($field['type']) {
            'checkbox' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'number' => is_numeric($value) ? $value + 0 : null,
            'file' => $value instanceof UploadedFile ? self::store($value, $form) : null,
            default => $field['multiple']
                ? array_values(array_map('strval', (array) $value))
                : (is_scalar($value) ? (string) $value : null),
        };
    }

    /**
     * Saves an uploaded answer on the private disk and returns its path, original name, and size.
     *
     * @return array{path: string, name: string, size: int}
     */
    private static function store(UploadedFile $file, Form $form): array
    {
        $path = $file->store(self::FOLDER.'/'.$form->getKey(), self::DISK);

        return [
            'path' => (string) $path,
            'name' => self::filename($file),
            'size' => (int) $file->getSize(),
        ];
    }

    /**
     * The visitor's file name, cleaned for showing and for the download header.
     *
     * The stored file gets a random name from Laravel; this one is only shown.
     */
    private static function filename(UploadedFile $file): string
    {
        $name = self::sanitize(basename(str_replace('\\', '/', $file->getClientOriginalName())));
        $name = trim((string) preg_replace('/[\/:*?"<>|]+/u', '_', $name), ' .');

        return $name !== '' ? Str::limit($name, 200, '') : 'file.'.($file->guessExtension() ?? 'bin');
    }

    /**
     * A cleaned answer in the shape its type expects.
     */
    private static function normalize(string $type, string $value): string
    {
        return match ($type) {
            'email' => mb_strtolower($value),
            'phone' => (string) preg_replace('/[\s\-().]/u', '', self::latin($value)),
            'number' => str_replace(['٫', '٬', ','], ['.', '', ''], self::latin($value)),
            'date' => str_replace('/', '-', self::latin($value)),
            default => $value,
        };
    }

    /**
     * Whether a key has the allowed shape and length.
     */
    private static function named(?string $key): bool
    {
        return $key !== null && strlen($key) <= self::LIMIT && preg_match(self::PATTERN, $key) === 1;
    }

    /**
     * Whether an answer meets a condition value; a list of answers meets it when one of them does.
     */
    private static function matches(mixed $answer, string $value): bool
    {
        if (is_array($answer)) {
            return in_array($value, array_map(fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $answer), true);
        }

        if (is_bool($answer)) {
            return $answer === filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return is_scalar($answer) && trim((string) $answer) === $value;
    }

    /**
     * The condition of a block, or null when it has none.
     *
     * @param  array<string, mixed>  $data
     * @return array{field: string, value: string}|null
     */
    private static function condition(array $data): ?array
    {
        $field = self::clear($data['when'] ?? null);
        $value = self::clear($data['equals'] ?? null);

        return self::named($field) && $value !== null ? ['field' => (string) $field, 'value' => $value] : null;
    }

    /**
     * The options of a choice field as a clean list of distinct texts.
     *
     * @return list<string>
     */
    private static function options(mixed $options): array
    {
        $list = array_map(fn (mixed $option): ?string => self::clear($option), is_array($options) ? $options : []);

        return array_values(array_unique(array_filter($list, fn (?string $option): bool => $option !== null)));
    }

    /**
     * The extensions of the chosen file kinds; every kind when none is chosen.
     *
     * @return list<string>
     */
    private static function accept(mixed $kinds): array
    {
        $kinds = array_values(array_intersect(is_array($kinds) ? $kinds : [], array_keys(self::EXTENSIONS)));
        $kinds = $kinds !== [] ? $kinds : array_keys(self::EXTENSIONS);

        return array_values(array_merge(...array_map(fn (string $kind): array => self::EXTENSIONS[$kind], $kinds)));
    }

    /**
     * A number typed in the builder, or null.
     */
    private static function number(mixed $value): int|float|null
    {
        if (is_string($value)) {
            $value = self::latin(trim($value));
        }

        return is_numeric($value) ? $value + 0 : null;
    }

    /**
     * Sanitized text, or null when blank.
     */
    private static function clear(mixed $value, bool $multiline = false): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = self::sanitize((string) $value, $multiline);

        return $value === '' ? null : $value;
    }
}
