<?php

namespace App\Filament\Auth;

use App\Auth\AccessTokens;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Panel login by username or email.
 *
 * After a successful login, a Sanctum token with the panel ability is queued in a secure cookie.
 * When "remember me" is on, the cookie lifetime is remember_expiration. Otherwise it is panel_expiration.
 *
 * Filament's Login page owns these method names. Renaming them breaks the override.
 */
class Login extends BaseLogin
{
    /**
     * Replaces the default email field with one identifier that accepts a username or an email.
     *
     * Usernames cannot contain @, so a value that validates as an email is looked up in the email column.
     */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('نام کاربری / ایمیل')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * Builds credentials for the web guard from the shared login field.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $login = $data['login'];

        return [
            (filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username') => $login,
            'password' => $data['password'],
        ];
    }

    /**
     * After Filament signs the user in, records the last login and sends the panel token cookie.
     *
     * If the parent authenticate call fails, no cookie is created.
     */
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

            cookie()->queue(app(AccessTokens::class)->cookie($user, $minutes));
        }

        return $response;
    }

    /**
     * Blocks an inactive account after the password has matched.
     *
     * Filament owns this method name. The message stays Persian so the person
     * knows the account was switched off, not that the password was wrong.
     */
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if ($user instanceof User && ! $user->active) {
            throw ValidationException::withMessages([
                'data.login' => 'این حساب غیرفعال است.',
            ]);
        }

        return parent::isUserAllowedToAccessPanel($user);
    }

    /**
     * Shows a failed login on the shared username or email field.
     */
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    /**
     * Sets the password field label to Persian.
     */
    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->label('رمز عبور');
    }

    /**
     * Shows the flash message left by install under the login page heading.
     */
    public function getSubheading(): string|Htmlable|null
    {
        $status = session('status');

        return filled($status) ? $status : null;
    }
}
