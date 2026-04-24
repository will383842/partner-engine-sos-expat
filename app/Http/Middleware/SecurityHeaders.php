<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add security-related HTTP headers to all responses.
 *
 * These headers are the Laravel-side safety net. In production, the same
 * headers should be set at the Nginx level for defense in depth.
 *
 * Policy rationale:
 *   - HSTS: force HTTPS for 2 years, include subdomains, preload-ready
 *   - X-Frame-Options: prevent clickjacking
 *   - X-Content-Type-Options: prevent MIME sniffing
 *   - Referrer-Policy: reduce info leakage to external sites
 *   - Permissions-Policy: disable geolocation/camera/microphone by default
 *   - CSP: strict but compatible with Tailwind CDN + Firebase SDK
 *     (Stripe JS allowed since /mon-acces may show Stripe-hosted pages)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=63072000; includeSubDomains; preload'
        );

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(self)'
        );

        // CSP is only applied to HTML responses — not API JSON
        $contentType = $response->headers->get('Content-Type', '');
        if (str_contains($contentType, 'text/html')) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; " .
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://www.gstatic.com https://js.stripe.com https://*.googleapis.com https://*.firebaseio.com; " .
                "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://fonts.bunny.net; " .
                "font-src 'self' data: https://fonts.bunny.net; " .
                "img-src 'self' data: https:; " .
                "connect-src 'self' https://cdn.jsdelivr.net https://www.gstatic.com https://*.firebaseio.com https://*.googleapis.com https://*.cloudfunctions.net https://*.run.app https://firestore.googleapis.com https://identitytoolkit.googleapis.com https://api.stripe.com; " .
                "frame-src 'self' https://js.stripe.com https://hooks.stripe.com; " .
                "form-action 'self'; " .
                "frame-ancestors 'none'; " .
                "base-uri 'self';"
            );
        }

        return $response;
    }
}
