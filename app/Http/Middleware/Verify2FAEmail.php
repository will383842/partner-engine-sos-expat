<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gates every admin page behind 2FA email verification when the logged-in
 * user has two_factor_email_enabled = true.
 *
 * Bypassed for:
 *   - the verify page itself (otherwise the user can't reach it)
 *   - logout endpoint
 */
class Verify2FAEmail
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !$user->two_factor_email_enabled) {
            return $next($request);
        }

        // Allow the user to reach the verify page + logout
        $allowedPaths = [
            'admin/2fa-verify',
            'admin/2fa-resend',
            'admin/logout',
        ];

        $path = ltrim($request->path(), '/');
        foreach ($allowedPaths as $allowed) {
            if ($path === $allowed) {
                return $next($request);
            }
        }

        // Already verified in this session?
        if ($request->session()->has('2fa_email_verified_at')) {
            return $next($request);
        }

        return redirect('/admin/2fa-verify');
    }
}
