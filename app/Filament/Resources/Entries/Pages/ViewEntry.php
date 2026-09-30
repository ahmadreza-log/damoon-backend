<?php

namespace App\Filament\Resources\Entries\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Entries\EntryResource;
use App\Filament\Resources\Forms\FormResource;
use App\Filament\Schemas\FormFields;
use App\Models\Entry;
use App\Support\Fields;
use App\Support\Shamsi;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Morilog\Jalali\Jalalian;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One message, laid out like a mail client. Opening a new message marks it read.
 *
 * The view (filament.pages.entry) shows a sender card with reply and call buttons, the
 * answers with a copy button each and uploaded files as cards with their own download
 * button, and a side column with where the message came from and the sender's other
 * messages. The header moves to the newer or older message and holds the status actions.
 * Files live on the private disk, so they are only reached through the download action.
 *
 * Extending:
 * - A new answer type shows through rows(); give it an icon in FormFields::ICONS.
 * - Filament owns the mount, getHeaderActions, and getViewData method names; downloadAction is
 *   Filament's naming for the action called download.
 */
class ViewEntry extends ViewRecord
{
    /** The resource whose model, labels, and actions this page uses. */
    protected static string $resource = EntryResource::class;

    /** The mail-like layout of the message. */
    protected string $view = 'filament.pages.entry';

    /**
     * Loads the message and marks it read when it was new.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        $entry = $this->entry();

        if ($entry->status === Entry::NEW && EntryResource::canEdit($entry)) {
            $entry->mark(Entry::READ);
        }
    }

    /**
     * The message subject in the page heading.
     */
    public function getTitle(): string
    {
        return 'پیام از «'.$this->sender()['name'].'»';
    }

    /**
     * Newer and older message, status changes, and delete.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        $newer = $this->neighbour(true);
        $older = $this->neighbour(false);

        return [
            Action::make('newer')
                ->label('پیام جدیدتر')
                ->tooltip('پیام جدیدتر')
                ->icon(Heroicon::OutlinedChevronRight)
                ->iconButton()
                ->color('gray')
                ->disabled($newer === null)
                ->url($newer === null ? null : EntryResource::getUrl('view', ['record' => $newer])),
            Action::make('older')
                ->label('پیام قدیمی‌تر')
                ->tooltip('پیام قدیمی‌تر')
                ->icon(Heroicon::OutlinedChevronLeft)
                ->iconButton()
                ->color('gray')
                ->disabled($older === null)
                ->url($older === null ? null : EntryResource::getUrl('view', ['record' => $older])),
            EntryResource::status('unread', 'خوانده نشده', Entry::NEW, Heroicon::OutlinedEnvelope)->color('gray'),
            EntryResource::status('archive', 'بایگانی', Entry::ARCHIVED, Heroicon::OutlinedArchiveBox)->color('gray'),
            EntryResource::status('restore', 'خروج از بایگانی', Entry::READ, Heroicon::OutlinedArrowUturnLeft)->color('gray'),
            DeleteAction::make()
                ->modalDescription('فایل‌های این پیام هم حذف می‌شوند.'),
        ];
    }

    /**
     * Downloads the file of one answer; the answer's index comes as the index argument.
     */
    public function downloadAction(): Action
    {
        return Action::make('download')
            ->label('دانلود')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->size('sm')
            ->action(function (array $arguments): ?StreamedResponse {
                $file = $this->file((int) ($arguments['index'] ?? -1));

                if ($file === null || ! Storage::disk(Fields::DISK)->exists($file['path'])) {
                    return null;
                }

                return Storage::disk(Fields::DISK)->download($file['path'], $file['name']);
            });
    }

    /**
     * Everything the view draws.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $rows = $this->rows();

        return [
            'entry' => $this->entry(),
            'sender' => $this->sender(),
            'rows' => $rows,
            'answered' => count(array_filter($rows, fn (array $row): bool => ! $row['empty'])),
            'details' => $this->details(),
            'history' => $this->history(),
            'transcript' => $this->transcript($rows),
        ];
    }

    /**
     * The record as a message.
     */
    private function entry(): Entry
    {
        $entry = $this->getRecord();

        abort_unless($entry instanceof Entry, 404);

        return $entry;
    }

    /**
     * Who sent the message: a name, an email and phone when valid, and the initial for the avatar.
     *
     * The name is the first text answer that looks like a name, then the signed-in customer.
     *
     * @return array{name: string, initial: string, email: string|null, phone: string|null, reply: string|null, customer: string|null}
     */
    private function sender(): array
    {
        $entry = $this->entry();
        $name = null;
        $email = null;
        $phone = null;

        foreach ((array) $entry->answers as $answer) {
            $type = (string) ($answer['type'] ?? '');
            $value = $answer['value'] ?? null;

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $key = (string) ($answer['key'] ?? '');
            $label = (string) ($answer['label'] ?? '');

            if ($name === null && $type === 'text' && (str_contains($key, 'name') || str_contains($label, 'نام'))) {
                $name = trim($value);
            }

            if ($email === null && $type === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
                $email = $value;
            }

            if ($phone === null && $type === 'phone' && preg_match('/^\+?[0-9]{6,15}$/', $value) === 1) {
                $phone = $value;
            }
        }

        $customer = $entry->customer;
        $full = trim(($customer->firstname ?? '').' '.($customer->lastname ?? ''));
        $name ??= $full !== '' ? $full : ($customer->username ?? null);
        $email ??= filter_var($customer->email ?? null, FILTER_VALIDATE_EMAIL) !== false ? $customer->email : null;
        $phone ??= preg_match('/^\+?[0-9]{6,15}$/', (string) ($customer->phone ?? '')) === 1 ? $customer->phone : null;
        $name ??= 'بازدیدکنندهٔ ناشناس';

        return [
            'name' => $name,
            'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
            'email' => $email,
            'phone' => $phone,
            'reply' => $email === null ? null : 'mailto:'.$email.'?subject='.rawurlencode('پاسخ: '.($entry->form->title ?? 'پیام شما')),
            'customer' => $customer !== null && CustomerResource::canEdit($customer) ? CustomerResource::getUrl('edit', ['record' => $customer]) : null,
        ];
    }

    /**
     * One row per answer, in the shape the view draws it.
     *
     * @return list<array{index: int, label: string, kind: string, icon: Heroicon, empty: bool, text: string, items: list<string>, tick: bool|null, link: string|null, external: bool, extra: string|null, multiline: bool, file: array{name: string, size: string|null, extension: string, present: bool}|null}>
     */
    private function rows(): array
    {
        $entry = $this->entry();
        $rows = [];

        foreach (array_values((array) $entry->answers) as $index => $answer) {
            $type = (string) ($answer['type'] ?? '');
            $value = $answer['value'] ?? null;
            $text = Fields::text($value);
            $file = $type === 'file' ? $this->file($index) : null;

            $rows[] = [
                'index' => $index,
                'label' => (string) ($answer['label'] ?? $answer['key'] ?? ''),
                'kind' => Fields::TYPES[$type] ?? $type,
                'icon' => FormFields::ICONS[$type] ?? Heroicon::OutlinedMinus,
                'empty' => $type === 'checkbox' ? ! is_bool($value) : $text === '—',
                'text' => $text,
                'items' => is_array($value) && ! isset($value['path']) ? array_values(array_map(fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $value)) : [],
                'tick' => is_bool($value) ? $value : null,
                'link' => $this->link($type, $text),
                'external' => $type === 'url',
                'extra' => $type === 'date' ? $this->shamsi($text) : null,
                'multiline' => $type === 'textarea',
                'file' => $file === null ? null : [
                    'name' => $file['name'],
                    'size' => $file['size'] === null ? null : $this->size($file['size']),
                    'extension' => strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'FILE',
                    'present' => Storage::disk(Fields::DISK)->exists($file['path']),
                ],
            ];
        }

        return $rows;
    }

    /**
     * The stored file of one answer, or null when it has none or its path leaves the form's folder.
     *
     * @return array{path: string, name: string, size: int|null}|null
     */
    private function file(int $index): ?array
    {
        $entry = $this->entry();
        $value = array_values((array) $entry->answers)[$index]['value'] ?? null;
        $path = is_array($value) ? ($value['path'] ?? null) : null;

        if (! is_string($path) || ! Fields::inside($path, $entry->form_id)) {
            return null;
        }

        return [
            'path' => $path,
            'name' => str_replace(['/', '\\'], '_', (string) ($value['name'] ?? basename($path))),
            'size' => is_numeric($value['size'] ?? null) ? (int) $value['size'] : null,
        ];
    }

    /**
     * A file size with a Persian unit, such as «239.3 کیلوبایت».
     */
    private function size(int $bytes): string
    {
        $units = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
        $step = 0;
        $amount = (float) max(0, $bytes);

        while ($amount >= 1024 && $step < count($units) - 1) {
            $amount /= 1024;
            $step++;
        }

        return number_format($amount, $step === 0 ? 0 : 1).' '.$units[$step];
    }

    /**
     * A mail, phone, or web link for an answer whose type has one and whose value is safe.
     */
    private function link(string $type, string $text): ?string
    {
        return match (true) {
            $text === '—' => null,
            $type === 'email' => filter_var($text, FILTER_VALIDATE_EMAIL) !== false ? 'mailto:'.$text : null,
            $type === 'phone' => preg_match('/^\+?[0-9]{6,15}$/', $text) === 1 ? 'tel:'.$text : null,
            $type === 'url' => Fields::link($text),
            default => null,
        };
    }

    /**
     * A stored Gregorian date answer as a Jalali date, or null when it is not a date.
     */
    private function shamsi(string $text): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) !== 1) {
            return null;
        }

        try {
            return Jalalian::fromCarbon(Carbon::createFromFormat('Y-m-d', $text))->format(Shamsi::DATE);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The side column: form, status, time, customer, page, IP, and browser.
     *
     * @return array{form: string, formurl: string|null, inbox: string|null, status: string, colour: string, date: string, ago: string, page: string|null, pageurl: string|null, ip: string|null, browser: string|null, agent: string|null, mobile: bool}
     */
    private function details(): array
    {
        $entry = $this->entry();
        $form = $entry->form;
        $time = $entry->created_at?->copy()->timezone(Shamsi::ZONE);
        $page = Fields::link($entry->source);
        $parts = $page === null ? [] : (array) parse_url($page);

        return [
            'form' => $form->title ?? 'فرم حذف‌شده',
            'formurl' => $form !== null && FormResource::canEdit($form) ? FormResource::getUrl('edit', ['record' => $form]) : null,
            'inbox' => $form === null ? null : EntryResource::getUrl('index', ['filters' => ['form_id' => ['value' => $form->getKey()]]]),
            'status' => Entry::statuses()[$entry->status] ?? $entry->status,
            'colour' => EntryResource::colour($entry->status),
            'date' => $time === null ? '—' : Jalalian::fromCarbon($time)->format('l j F Y، ساعت H:i'),
            'ago' => $time === null ? '' : $time->locale('fa')->diffForHumans(),
            'page' => $page === null ? null : ($parts['host'] ?? '').(($parts['path'] ?? '/') === '/' ? '' : $parts['path']),
            'pageurl' => $page,
            'ip' => $entry->ip,
            'browser' => $this->browser((string) $entry->agent),
            'agent' => $entry->agent,
            'mobile' => preg_match('/Mobile|Android|iPhone|iPad/i', (string) $entry->agent) === 1,
        ];
    }

    /**
     * The browser and system named in a user agent, such as «Chrome روی Windows».
     */
    private function browser(string $agent): ?string
    {
        if (trim($agent) === '') {
            return null;
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };

        $system = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return match (true) {
            $browser !== null && $system !== null => $browser.' روی '.$system,
            $browser !== null => $browser,
            $system !== null => $system,
            default => null,
        };
    }

    /**
     * The sender's five latest other messages, matched by customer or by email.
     *
     * @return list<array{url: string, form: string, summary: string, ago: string, status: string, colour: string}>
     */
    private function history(): array
    {
        $entry = $this->entry();
        $email = $this->sender()['email'];

        if ($entry->customer_id === null && $email === null) {
            return [];
        }

        return Entry::query()
            ->with('form')
            ->whereKeyNot($entry->getKey())
            ->where(function (Builder $query) use ($entry, $email): void {
                if ($entry->customer_id !== null) {
                    $query->orWhere('customer_id', $entry->customer_id);
                }

                if ($email !== null) {
                    $query->orWhereRaw("LOWER(CAST(answers AS TEXT)) LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) json_encode(mb_strtolower($email))).'%']);
                }
            })
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Entry $other): array => [
                'url' => EntryResource::getUrl('view', ['record' => $other]),
                'form' => $other->form->title ?? 'فرم حذف‌شده',
                'summary' => $other->summary(),
                'ago' => $other->created_at?->locale('fa')->diffForHumans() ?? '',
                'status' => Entry::statuses()[$other->status] ?? $other->status,
                'colour' => EntryResource::colour($other->status),
            ])
            ->all();
    }

    /**
     * The message next to this one in the inbox order (newest first), or null at either end.
     */
    private function neighbour(bool $newer): ?Entry
    {
        $entry = $this->entry();
        $time = $entry->created_at;

        if ($time === null) {
            return null;
        }

        $sign = $newer ? '>' : '<';

        return Entry::query()
            ->where(fn (Builder $query) => $query
                ->where('created_at', $sign, $time)
                ->orWhere(fn (Builder $same) => $same->where('created_at', $time)->where('id', $sign, $entry->getKey())))
            ->orderBy('created_at', $newer ? 'asc' : 'desc')
            ->orderBy('id', $newer ? 'asc' : 'desc')
            ->first();
    }

    /**
     * Every answer as «label: value» lines, for the copy-all button.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function transcript(array $rows): string
    {
        $lines = array_map(fn (array $row): string => $row['label'].': '.$row['text'], $rows);

        return implode("\n", $lines);
    }
}
