<?php

namespace App\Filament\Pages;

use App\Auth\Section;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The panel home page.
 *
 * It is hidden unless the signed-in user may open the home section.
 * The owner always may, through Gate::before.
 *
 * Extending:
 * - Filament owns canAccess and the route path inherited from the base dashboard.
 * - Widgets stay registered on the panel provider.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'پیشخوان';

    protected static ?string $title = 'پیشخوان';

    /**
     * Shows the home item only for accounts that may open it.
     *
     * Filament owns this method name.
     */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can(Section::HOME);
    }
}
