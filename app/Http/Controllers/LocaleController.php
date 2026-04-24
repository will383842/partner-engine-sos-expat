<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Language switcher for the partner and admin Filament panels.
 *
 * The chosen locale is persisted as a `locale` cookie with a 1-year TTL
 * and read back on every request by SetLocaleFromCookie middleware.
 *
 *   POST /locale/fr  -> set French
 *   POST /locale/en  -> set English
 *
 * Redirects back to the referer (dashboard, any resource page, etc.).
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $supported = array_keys(config('locales.enabled', ['fr' => [], 'en' => []]));
        if (!in_array($locale, $supported, true)) {
            $locale = config('locales.default', 'fr');
        }

        Cookie::queue(
            cookie(
                name: 'locale',
                value: $locale,
                minutes: 60 * 24 * 365, // 1 year
                path: '/',
                secure: true,
                httpOnly: false, // read by JS too if needed
                sameSite: 'lax'
            )
        );

        return back();
    }
}
