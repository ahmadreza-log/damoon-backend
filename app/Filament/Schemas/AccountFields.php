<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

class AccountFields
{
    /**
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
