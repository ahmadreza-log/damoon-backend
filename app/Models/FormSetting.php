<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * The forms settings (تنظیمات فرم‌ها): one row, edited on the forms settings page.
 *
 * notify turns the email notice for new messages on or off, and recipients are the
 * addresses it goes to. message is the default text shown after sending. rate is how many
 * messages one IP may send a minute, size is the largest uploaded file in kilobytes, and
 * retention is how many days messages are kept; empty keeps them forever.
 * Until the page is saved, current returns an unsaved row with the defaults.
 *
 * Extending:
 * - A new setting is a column on form_settings, a key in Fillable and DEFAULTS, and a field on the settings page.
 */
#[Fillable([
    'notify',
    'recipients',
    'message',
    'rate',
    'size',
    'retention',
])]
class FormSetting extends Model
{
    /** The text shown after sending when neither the form nor the settings set one. */
    public const MESSAGE = 'پیام شما با موفقیت ارسال شد.';

    /** The values used before the settings page is first saved. */
    public const DEFAULTS = [
        'notify' => true,
        'recipients' => [],
        'message' => null,
        'rate' => 5,
        'size' => 5120,
        'retention' => null,
    ];

    /** The limits the settings page allows. */
    public const RATES = [1, 60];

    public const SIZES = [100, 51200];

    /**
     * The saved settings row, or an unsaved one with the defaults.
     */
    public static function current(): self
    {
        return static::query()->oldest('id')->first() ?? new self(self::DEFAULTS);
    }

    /**
     * The default text shown after sending.
     */
    public function thanks(): string
    {
        return filled($this->message) ? (string) $this->message : self::MESSAGE;
    }

    /**
     * The valid notice addresses.
     *
     * @return list<string>
     */
    public function addresses(): array
    {
        return array_values(array_filter((array) $this->recipients, fn (mixed $email): bool => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false));
    }

    /**
     * recipients is a JSON list; the numbers are integers.
     *
     * Eloquent owns this method name.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notify' => 'boolean',
            'recipients' => 'array',
            'rate' => 'integer',
            'size' => 'integer',
            'retention' => 'integer',
        ];
    }
}
