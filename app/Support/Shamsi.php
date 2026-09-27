<?php

namespace App\Support;

use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Table;

/**
 * Shamsi dates for the whole panel.
 *
 * Stored timestamps stay in the application timezone. The panel shows them
 * on the Iran clock, as a Jalali date. Date columns use jalaliDateTime.
 * Date pickers use the Jalali calendar automatically.
 *
 * Extending:
 * - Change DATE or TIME here. Tables, schemas, and pickers read those formats.
 * - Call jalaliDateTime with ZONE on any new table column that shows a timestamp.
 */
class Shamsi
{
    /** Iran clock, used when a stored timestamp is shown. */
    public const ZONE = 'Asia/Tehran';

    /** Jalali date, for example ۱۴۰۵/۰۷/۰۵. */
    public const DATE = 'Y/m/d';

    /** Jalali date and time, for example ۱۴۰۵/۰۷/۰۵ ۱۴:۳۰:۰۰. */
    public const TIME = 'Y/m/d H:i:s';

    /**
     * Applies the Shamsi formats and the Jalali date picker.
     *
     * Call this after the Jalali package has booted, so the picker macro exists
     * by the time a form is built.
     */
    public static function boot(): void
    {
        FilamentTimezone::set(self::ZONE);

        Table::configureUsing(function (Table $table): void {
            $table
                ->defaultDateDisplayFormat(self::DATE)
                ->defaultDateTimeDisplayFormat(self::TIME);
        });

        Schema::configureUsing(function (Schema $schema): void {
            $schema
                ->defaultDateDisplayFormat(self::DATE)
                ->defaultDateTimeDisplayFormat(self::TIME);
        });

        DateTimePicker::configureUsing(function (DateTimePicker $picker): void {
            $picker
                ->jalali()
                ->defaultDateDisplayFormat(self::DATE)
                ->defaultDateTimeDisplayFormat('Y/m/d H:i')
                ->defaultDateTimeWithSecondsDisplayFormat(self::TIME);
        });
    }
}
