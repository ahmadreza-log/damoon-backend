<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Http\Middleware\AuthenticatePanelToken;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\SetPersianLocale;
use App\Models\Setting;
use App\Support\Seo;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The staff panel at /admin.
 *
 * It is Persian and right to left, set in Iran Yekan, with a navy scale around #00377B as the
 * primary color and slate greys. The logo is the brand mark in filament.brand, and the panel's
 * own look (glass topbar, sidebar pill, cards, login stage) is resources/css/panel.css,
 * registered in AppServiceProvider. The sidebar can be folded to icons on desktop.
 * Until install finishes, EnsureInstalled sends every request to /install.
 * AuthenticatePanelToken builds the session from the Sanctum cookie.
 * spa keeps CSS, JavaScript, and fonts loaded while moving between panel pages.
 * Link prefetching is on only in production. php artisan serve has one worker on Windows,
 * so hover prefetches would queue in front of the page that was actually clicked.
 * The sidebar groups are دسترسی, then محتوا, then فرم‌ها.
 * The media picker styles are added to the page head, since the panel has no custom theme.
 *
 * Extending:
 * - Put a new resource in app/Filament/Resources. discoverResources picks it up.
 * - The home page is app/Filament/Pages/Dashboard. discoverPages picks it up.
 * - Dashboard widgets (Welcome, Overview) live in app/Filament/Widgets. discoverWidgets picks them up.
 * - Keep panel middleware after StartSession and before AuthenticateSession.
 * - Filament owns the panel method name.
 */
class AdminPanelProvider extends PanelProvider
{
    /**
     * The brand navy as a full scale: 600 is #00377B, lighter shades above it and darker ones below,
     * so hover, active, and dark-mode accents (which use 400 and 500) stay readable.
     */
    private const PRIMARY = [
        50 => 'oklch(0.97 0.014 258)',
        100 => 'oklch(0.93 0.032 258)',
        200 => 'oklch(0.86 0.06 258)',
        300 => 'oklch(0.76 0.1 258)',
        400 => 'oklch(0.64 0.14 258)',
        500 => 'oklch(0.5 0.16 258)',
        600 => '#00377B',
        700 => 'oklch(0.29 0.11 258)',
        800 => 'oklch(0.25 0.09 258)',
        900 => 'oklch(0.21 0.07 258)',
        950 => 'oklch(0.16 0.05 258)',
    ];

    /**
     * Builds the panel appearance, path, login, and middleware.
     *
     * The brand title comes from Setting::brand, or the application name when install has not run.
     * The same name is the site name in the SEO box previews, through Seo::brand.
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->spa(hasPrefetching: app()->isProduction())
            ->font('iranyekan', asset('fonts/iranyekan/iranyekan.css'), LocalFontProvider::class)
            ->brandName(fn (): string => Setting::brand())
            ->brandLogo(fn (): View => view('filament.brand'))
            ->brandLogoHeight('2.5rem')
            ->bootUsing(Seo::brand(...))
            ->colors([
                'primary' => self::PRIMARY,
                'gray' => Color::Slate,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('filament.fields.media-style'))
            ->navigationGroups([
                NavigationGroup::make('دسترسی'),
                NavigationGroup::make('محتوا'),
                NavigationGroup::make('فرم‌ها'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
