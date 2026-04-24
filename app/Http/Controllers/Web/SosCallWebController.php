<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public web controller for /sos-call — the client-facing landing page
 * where partner subscribers enter their SOS-Call code (or phone+email) to
 * trigger a free emergency call.
 *
 * Served at https://sos-call.sos-expat.com
 *
 * The page is a single-page Blade with Alpine.js state machine:
 *   initial → verifying → access_granted (choose expert/lawyer) → call_in_progress
 *                      → code_invalid | phone_match_email_mismatch | not_found
 *
 * The Blade page calls the Firebase callable `checkSosCallCode` which proxies
 * to Partner Engine's /api/sos-call/check. After a successful verification,
 * it calls the Firebase callable `createAndScheduleCall` with sosCallSessionToken
 * to trigger the Twilio call without any payment.
 */
class SosCallWebController extends Controller
{
    /**
     * GET / (on sos-call.sos-expat.com) or /sos-call (on main domain)
     */
    public function index(Request $request): View
    {
        $locale = $this->resolveLocale($request);
        app()->setLocale($locale);

        $clientConfig = [
            'locale' => $locale,
            'firebase' => $this->firebasePublicConfig(),
            'frontendUrl' => config('services.frontend_url'),
            'sosCallUrl' => config('services.sos_call.public_url'),
            'logoUrl' => 'https://sos-expat.com/sos-logo.webp',
            // Default standard SOS-Expat prices (Firestore admin_config/pricing).
            // The Blade page hydrates these dynamically from Firestore on load.
            'pricing' => [
                'expat' => ['eur' => 19, 'usd' => 25, 'duration' => 30],
                'lawyer' => ['eur' => 49, 'usd' => 55, 'duration' => 20],
            ],
        ];

        return view('sos-call.index', [
            'locale' => $locale,
            'clientConfigJson' => json_encode($clientConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG),
            'frontendUrl' => $clientConfig['frontendUrl'],
            'sosCallUrl' => $clientConfig['sosCallUrl'],
        ]);
    }

    /**
     * Pick locale from ?lang= parameter, or Accept-Language header, or fallback to 'fr'.
     */
    protected function resolveLocale(Request $request): string
    {
        $supported = ['fr', 'en', 'es', 'de', 'pt', 'ar', 'zh', 'ru', 'hi'];

        // 1. Explicit query param
        $fromQuery = $request->query('lang');
        if (is_string($fromQuery) && in_array($fromQuery, $supported, true)) {
            return $fromQuery;
        }

        // 2. Accept-Language header (take the first supported)
        $accept = $request->header('Accept-Language', '');
        if ($accept) {
            $parts = explode(',', $accept);
            foreach ($parts as $part) {
                $lang = strtolower(trim(explode(';', $part)[0] ?? ''));
                $lang = substr($lang, 0, 2);
                if (in_array($lang, $supported, true)) {
                    return $lang;
                }
            }
        }

        return 'fr';
    }

    /**
     * Public Firebase web config to pass to the Blade page.
     * These values are safe to expose (they're already in the public SOS-Expat SPA).
     */
    protected function firebasePublicConfig(): array
    {
        return [
            'apiKey' => config('services.firebase.web_api_key', env('FIREBASE_WEB_API_KEY', '')),
            'authDomain' => config('services.firebase.web_auth_domain', env('FIREBASE_WEB_AUTH_DOMAIN', '')),
            'projectId' => config('services.firebase.project_id', env('FIREBASE_PROJECT_ID', '')),
            'region' => 'us-central1', // Same as partnerConfig in functionConfigs.ts
        ];
    }
}
