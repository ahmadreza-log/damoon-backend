<?php

namespace App\Filament\Auth;

use App\Auth\AccessTokens;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('کاربری')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();
        $user = Auth::user();

        if ($response && $user instanceof User) {
            $remember = (bool) ($this->form->getState()['remember'] ?? false);
            $minutes = $remember ? (int) config('sanctum.remember_expiration') : (int) config('sanctum.panel_expiration');

            $user->forceFill([
                'last_login' => now(),
                'last_login_ip' => request()->ip(),
            ])->save();

            cookie()->queue(app(AccessTokens::class)->panelCookie($user, $minutes));
        }

        return $response;
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->label('رمز');
    }

    public function getSubheading(): string|Htmlable|null
    {
        $status = session('status');

        return filled($status) ? $status : null;
    }
}
