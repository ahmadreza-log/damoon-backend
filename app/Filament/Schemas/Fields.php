<?php

namespace App\Filament\Schemas;

use App\Filament\Fields\MediaPicker;
use App\Models\Degree;
use App\Models\Gender;
use App\Models\User;
use App\Rules\National;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Hash;

/**
 * Form fields for users and customers.
 *
 * account is shared. avatar, staff, password, and status are only on the user form.
 *
 * Extending:
 * - Add a shared field in account, then also in both models, the migration, and the table column.
 * - Add a staff-only field in staff, User fillable, and a users migration. Do not add it on the customer form.
 * - Degree levels live on App\Models\Degree. The dropdown stores the English key.
 * - clear turns a blank box into null so two empty unique codes do not collide.
 * - The avatar file is public. storage:link must exist. User::getFilamentAvatarUrl owns the panel image.
 * - current and confirmation are not columns. Only password is saved, and only when filled.
 * - status writes users.active. Login and AuthenticatePanelToken both refuse a false value. The owner stays active.
 */
class Fields
{
    /**
     * Shared name, username, email, phone, and the customer password.
     *
     * The user form passes false and draws password before the section checklist.
     * Password is required only on create. On edit, an empty value means the password stays unchanged.
     *
     * @return array<int, Component>
     */
    public static function account(bool $password = true): array
    {
        $fields = [
            TextInput::make('firstname')
                ->label('نام')
                ->required()
                ->maxLength(255),
            TextInput::make('lastname')
                ->label('نام خانوادگی')
                ->required()
                ->maxLength(255),
            TextInput::make('username')
                ->label('نام کاربری')
                ->required()
                ->maxLength(255)
                ->rule('alpha_dash:ascii')
                ->unique(ignoreRecord: true),
            TextInput::make('email')
                ->label('ایمیل')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('phone')
                ->label('شماره تلفن')
                ->columnSpan(2)
                ->tel()
                ->maxLength(20)
                ->unique(ignoreRecord: true),
        ];

        if ($password) {
            $fields[] = TextInput::make('password')
                ->label('رمز عبور')
                ->password()
                ->revealable()
                ->rule('min:8')
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->maxLength(255);
        }

        return $fields;
    }

    /**
     * The avatar picker shown at the top of the user form. It opens the media library.
     */
    public static function avatar(): Component
    {
        return MediaPicker::make('avatar')
            ->label('آواتار')
            ->round()
            ->directory('avatars')
            ->columnSpanFull();
    }

    /**
     * The personnel section shown on the user form.
     */
    public static function staff(): Component
    {
        return Section::make('مشخصات پرسنلی')
            ->columns(2)
            ->columnSpan(2)
            ->schema([
                TextInput::make('personnel')
                    ->label('کد پرسنلی')
                    ->nullable()
                    ->maxLength(32)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (mixed $state): ?string => self::clear($state)),
                TextInput::make('national')
                    ->label('کد ملی')
                    ->nullable()
                    ->maxLength(10)
                    ->rule(new National)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (mixed $state): ?string => self::clear($state)),
                TextInput::make('job')
                    ->label('موقعیت شغلی')
                    ->nullable()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->dehydrateStateUsing(fn (mixed $state): ?string => self::clear($state)),
                Select::make('degree')
                    ->label('مدرک تحصیلی')
                    ->options(Degree::options())
                    ->native(false)
                    ->nullable()
                    ->in(array_keys(Degree::options()))
                    ->placeholder('انتخاب کنید'),
                TextInput::make('major')
                    ->label('رشته تحصیلی')
                    ->nullable()
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn (mixed $state): ?string => self::clear($state)),
                ToggleButtons::make('gender')
                    ->label('جنسیت')
                    ->options(Gender::options())
                    ->inline()
                    ->nullable()
                    ->in(array_keys(Gender::options()))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The password section shown on the user form.
     *
     * Create asks for a new password and a repeat. Edit also asks for the password
     * already stored on that user. Leaving the box empty on edit keeps the hash.
     */
    public static function password(): Component
    {
        return Section::make('رمز عبور')
            ->columnSpan(2)
            ->columns(3)
            ->schema([
                TextInput::make('current')
                    ->columnSpan(1)
                    ->label('رمز عبور فعلی')
                    ->password()
                    ->revealable()
                    ->autocomplete('current-password')
                    ->visibleOn('edit')
                    ->dehydrated(false)
                    ->required(fn (Get $get, string $operation): bool => $operation === 'edit' && (filled($get('password')) || filled($get('confirmation'))))
                    ->rule(fn (?User $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        self::known($record, $value, $fail);
                    }),
                TextInput::make('password')
                    ->columnSpan(1)
                    ->label('رمز عبور جدید')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->maxLength(255)
                    ->required(fn (Get $get, string $operation): bool => $operation === 'create' || filled($get('current')) || filled($get('confirmation')))
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        self::length($value, $fail);
                    }),
                TextInput::make('confirmation')
                    ->columnSpan(1)
                    ->label('تکرار رمز عبور')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->dehydrated(false)
                    ->required(fn (Get $get, string $operation): bool => $operation === 'create' || filled($get('password')) || filled($get('current')))
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        self::agree($get('password'), $value, $fail);
                    }),
            ]);
    }

    /**
     * The status buttons shown above the section checklist.
     *
     * Inactive blocks panel sign-in. The account row stays, and delete stays available.
     * The owner cannot be switched off.
     */
    public static function status(): Component
    {
        return ToggleButtons::make('active')
            ->label('وضعیت حساب')
            ->boolean('فعال', 'غیرفعال')
            ->inline()
            ->required()
            ->default(true)
            ->columnSpanFull()
            ->disabled(fn (?User $record): bool => (bool) $record?->owner())
            ->helperText(fn (?User $record): string => $record?->owner()
                ? 'مالک سامانه همیشه فعال است.'
                : 'با غیرفعال کردن، ورود این کاربر به پنل قطع می‌شود.');
    }

    /**
     * Stores a blank text box as null.
     */
    private static function clear(mixed $state): ?string
    {
        if (! is_string($state)) {
            return null;
        }

        $state = trim($state);

        return $state === '' ? null : $state;
    }

    /**
     * Rejects a previous password that does not match the stored hash.
     */
    private static function known(?User $record, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $hash = $record?->password;

        if (! is_string($hash) || ! Hash::check($value, $hash)) {
            $fail('رمز عبور فعلی درست نیست.');
        }
    }

    /**
     * Rejects a new password shorter than 8 characters.
     */
    private static function length(mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (mb_strlen($value) < 8) {
            $fail('رمز عبور جدید باید حداقل ۸ کاراکتر باشد.');
        }
    }

    /**
     * Rejects a repeat that is not the same as the new password.
     */
    private static function agree(mixed $password, mixed $again, Closure $fail): void
    {
        if (! filled($password) && ! filled($again)) {
            return;
        }

        if ((string) $password === (string) $again) {
            return;
        }

        $fail('تکرار رمز عبور با رمز عبور جدید یکسان نیست.');
    }
}
