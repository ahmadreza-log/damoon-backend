<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Icons;
use Illuminate\Http\JsonResponse;

/**
 * The site's social links from the social networks settings (تنظیمات شبکه‌های اجتماعی), version 1.
 *
 * Routes live under /v1/socials.
 *
 * Extending:
 * - A new field on each link is added in Setting::links and in the panel's repeater.
 */
class SocialController extends Controller
{
    /**
     * The social links in the order set in the panel.
     *
     * Each item has name, icon (the Blade Icons name, like si-instagram or brand-eitaa), url,
     * and svg: the icon's SVG markup, drawn with currentColor so it takes the text color.
     * Rows with a missing name, address, or icon are left out. data is empty when no link is set.
     *
     * @response array{data: list<array{name: string, icon: string, url: string, svg: string|null}>}
     */
    public function index(): JsonResponse
    {
        $links = array_map(fn (array $link): array => [
            ...$link,
            'svg' => Icons::markup($link['icon']),
        ], Setting::current()?->links() ?? []);

        return response()->json(['data' => $links]);
    }
}
