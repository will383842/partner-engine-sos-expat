<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads the `locale` cookie on every request (set by LocaleController)
 * and calls app()->setLocale() so Filament / Blade / translations pick it up.
 */
class SetLocaleFromCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('locales.enabled', ['fr' => [], 'en' => []]));
        $default = config('locales.default', 'fr');
        $locale = $request->cookie('locale', $default);
        if (in_array($locale, $supported, true)) {
            app()->setLocale($locale);
        }
        return $next($request);
    }
}
