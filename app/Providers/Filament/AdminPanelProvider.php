<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Http\Middleware\AuthenticatePanelToken;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\SetPersianLocale;
use App\Models\Setting;
use Filament\FontProviders\LocalFontProvider;
use Filament\Navigation\NavigationGroup;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The staff panel at /admin.
 *
 * It is Persian and right to left, set in Iran Yekan, with #00377B as the primary color.
 * Until install finishes, EnsureInstalled sends every request to /install.
 * AuthenticatePanelToken builds the session from the Sanctum cookie.
 * spa keeps CSS, JavaScript, and fonts loaded while moving between panel pages.
 * The sidebar groups are دسترسی, then محتوا.
 *
 * Extending:
 * - Put a new resource in app/Filament/Resources. discoverResources picks it up.
 * - The home page is app/Filament/Pages/Dashboard. discoverPages picks it up.
 * - Keep panel middleware after StartSession and before AuthenticateSession.
 * - Filament owns the panel method name.
 */
class AdminPanelProvider extends PanelProvider
{
    /**
     * Builds the panel appearance, path, login, and middleware.
     *
     * The brand title comes from Setting::current, or the application name when install has not run.
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->spa(hasPrefetching: true)
            ->font('iranyekan', asset('fonts/iranyekan/iranyekan.css'), LocalFontProvider::class)
            ->brandName(fn (): string => Setting::current()?->title ?: config('app.name'))
            ->colors([
                'primary' => array_replace(Color::hex('#00377B'), [
                    600 => '#00377B',
                ]),
            ])
            ->navigationGroups([
                NavigationGroup::make('دسترسی'),
                NavigationGroup::make('محتوا'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                SetPersianLocale::class,
                EnsureInstalled::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticatePanelToken::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
