<?php

namespace App\Models;

use App\Support\Fields;
use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A message (پیام) sent through a form, shown in the panel inbox.
 *
 * answers is a snapshot: each row keeps the field key, the label it had, its type, and the
 * value, so later edits to the form do not change old messages. A file answer's value holds
 * the path on the private disk, the original name, and the size. New messages are new until
 * someone opens them; staff may archive them. customer is set when the visitor sent a
 * customer token. source is the site page the message came from, when the browser said so.
 * With a retention period in the forms settings, php artisan model:prune deletes older
 * messages. Deleting a message deletes its files.
 *
 * Extending:
 * - A new status needs a constant, a label in statuses, a colour in EntryResource, and a tab in ListEntries.
 */
#[Fillable([
    'form_id',
    'customer_id',
    'answers',
    'status',
    'ip',
    'agent',
    'source',
])]
class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
    use HasFactory;

    use Prunable;

    /** Not opened in the panel yet. */
    public const NEW = 'new';

    /** Opened in the panel. */
    public const READ = 'read';

    /** Put aside by staff. */
    public const ARCHIVED = 'archived';

    /**
     * Persian status labels for the panel.
     *
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::NEW => 'جدید',
            self::READ => 'خوانده‌شده',
            self::ARCHIVED => 'بایگانی',
        ];
    }

    /**
     * Deletes the uploaded files with the message.
     *
     * Eloquent owns this method name.
     */
    protected static function booted(): void
    {
        static::deleted(function (Entry $entry): void {
            $paths = $entry->files();

            if ($paths !== []) {
                Storage::disk(Fields::DISK)->delete($paths);
            }
        });
    }

    /**
     * The form this message was sent through.
     *
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * The customer who sent it, when they were signed in.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Gives the message a status.
     */
    public function mark(string $status): void
    {
        $this->forceFill(['status' => $status])->save();
    }

    /**
     * The first answers as one line, for the inbox list.
     */
    public function summary(): string
    {
        $parts = [];

        foreach ((array) $this->answers as $answer) {
            $text = Fields::text($answer['value'] ?? null);

            if ($text !== '—' && ($answer['type'] ?? '') !== 'file') {
                $parts[] = $text;
            }

            if (count($parts) === 2) {
                break;
            }
        }

        return $parts === [] ? '—' : Str::limit(implode(' — ', $parts), 140);
    }

    /**
     * Paths of the files uploaded with this message; a path outside the form's upload folder is left out.
     *
     * @return list<string>
     */
    public function files(): array
    {
        $paths = [];

        foreach ((array) $this->answers as $answer) {
            $path = is_array($answer['value'] ?? null) ? ($answer['value']['path'] ?? null) : null;

            if (Fields::inside($path, $this->form_id)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Messages older than the retention period in the forms settings; none when it is empty.
     *
     * Laravel owns this method name.
     *
     * @return Builder<Entry>
     */
    public function prunable(): Builder
    {
        $days = FormSetting::current()->retention;

        return $days !== null && $days > 0
            ? static::query()->where('created_at', '<', now()->subDays($days))
            : static::query()->whereRaw('1 = 0');
    }

    /**
     * answers is a JSON list.
     *
     * Eloquent owns this method name.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
        ];
    }
}
