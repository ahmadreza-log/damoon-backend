<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/**
 * Shared account form for users and customers.
 *
 * Both panel resources render this list so the fields do not drift apart.
 *
 * Extending:
 * - Add a new field here, then also in the model Fillable, the migration, and the resource table column.
 * - unique with ignoreRecord skips the current record during edit.
 * - Password is required only on create. On edit, an empty value means the password stays unchanged.
 */
class AccountFields
{
    /**
     * Returns the account form components.
     *
     * @return array<int, Component>
     */
    public static function make(): array
    {
        return [
            TextInput::make('firstname')
                ->label('نام')
                ->required()
                ->maxLength(255),
            TextInput::make('lastname')
                ->label('خانوادگی')
                ->required()
                ->maxLength(255),
            TextInput::make('username')
                ->label('کاربری')
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
                ->label('تلفن')
                ->tel()
                ->maxLength(20)
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->label('رمز')
                ->password()
                ->revealable()
                ->rule('min:8')
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->maxLength(255),
        ];
    }
}
