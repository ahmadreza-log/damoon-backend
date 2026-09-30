<?php

namespace App\Filament\Resources\Entries\Pages;

use App\Filament\Resources\Entries\EntryResource;
use App\Models\Entry;
use App\Models\Form;
use App\Support\Fields;
use App\Support\Shamsi;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Morilog\Jalali\Jalalian;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Inbox index with a tab per status and the count of each, and the CSV export button.
 *
 * There is no create button: messages are sent from the site.
 * Filament owns the getTabs and getHeaderActions method names.
 */
class ListEntries extends ListRecords
{
    /** The resource whose table, model, and labels this page uses. */
    protected static string $resource = EntryResource::class;

    /**
     * All messages, then one tab per status.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Entry::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $tabs = ['all' => Tab::make('همه')];

        foreach (Entry::statuses() as $status => $label) {
            $count = (int) ($counts[$status] ?? 0);

            $tabs[$status] = Tab::make($label)
                ->badge($count > 0 ? $count : null)
                ->badgeColor(EntryResource::colour($status))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status));
        }

        return $tabs;
    }

    /**
     * The export button: one form's messages as a CSV file that Excel opens in Persian.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('خروجی CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->schema([
                    Select::make('form')
                        ->label('فرم')
                        ->options(fn (): array => Form::query()->orderBy('title')->pluck('title', 'id')->all())
                        ->required()
                        ->searchable()
                        ->native(false),
                ])
                ->modalSubmitActionLabel('دانلود')
                ->action(fn (array $data): StreamedResponse => $this->export(Form::query()->findOrFail($data['form']))),
        ];
    }

    /**
     * Streams every message of the form: date and status, then a column per field.
     *
     * The columns are the form's current fields, then any older field that only earlier
     * messages have, so nothing sent is left out. Every cell goes through Fields::cell, so
     * a spreadsheet never runs an answer as a formula.
     */
    private function export(Form $form): StreamedResponse
    {
        $entries = $form->entries()->oldest()->oldest('id')->get();
        $columns = [];

        foreach (Fields::inputs($form->definition()) as $field) {
            $columns[(string) $field['key']] = (string) $field['label'];
        }

        foreach ($entries as $entry) {
            foreach ((array) $entry->answers as $answer) {
                $columns[(string) $answer['key']] ??= (string) ($answer['label'] ?? $answer['key']);
            }
        }

        return response()->streamDownload(function () use ($entries, $columns): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(Fields::cell(...), ['تاریخ', 'وضعیت', ...array_values($columns)]));

            foreach ($entries as $entry) {
                $values = collect((array) $entry->answers)->keyBy('key');
                $row = [
                    $entry->created_at === null ? '' : Jalalian::fromCarbon($entry->created_at->timezone(Shamsi::ZONE))->format(Shamsi::TIME),
                    Entry::statuses()[$entry->status] ?? $entry->status,
                ];

                foreach (array_keys($columns) as $key) {
                    $row[] = $values->has($key) ? Fields::text($values[$key]['value'] ?? null) : '';
                }

                fputcsv($out, array_map(Fields::cell(...), $row));
            }

            fclose($out);
        }, $form->slug.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
