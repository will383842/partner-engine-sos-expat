<?php

namespace App\Http\Controllers;

use App\Mail\TwoFactorEmailCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Email-based 2FA for the Filament admin panel.
 *
 * Flow:
 *   1. User logs in with email + password (Filament's normal login).
 *   2. If user.two_factor_email_enabled = true, the Verify2FAEmail
 *      middleware redirects to /admin/2fa-verify before they can access
 *      any other admin page.
 *   3. On first hit of that page, sendCode() is called: a 6-digit code
 *      is generated, hashed, stored on the user with a 10-minute expiry,
 *      and emailed.
 *   4. User submits the code → verify() compares hashes; on success the
 *      session is marked 2fa_verified_at and the user is redirected to
 *      /admin.
 */
class TwoFactorEmailController extends Controller
{
    private const CODE_TTL_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    /**
     * Show the verify-code page. If no code has been sent yet (or the
     * existing one has expired), generate and email a new one.
     */
    public function show(Request $request)
    {
        $user = Auth::user();

        if (!$user || !$user->two_factor_email_enabled) {
            // 2FA not required; ship them straight to /admin.
            return redirect('/admin');
        }

        // Send a fresh code if none is active or it's expired.
        $needsNewCode = !$user->two_factor_email_code
            || !$user->two_factor_email_code_expires_at
            || Carbon::parse($user->two_factor_email_code_expires_at)->isPast();

        if ($needsNewCode) {
            $this->sendCode($user);
        }

        return view('two-factor-email.verify', [
            'user' => $user,
            'expiresInMinutes' => self::CODE_TTL_MINUTES,
        ]);
    }

    /**
     * Verify the code submitted by the user.
     */
    public function verify(Request $request)
    {
        $user = Auth::user();

        if (!$user || !$user->two_factor_email_enabled) {
            return redirect('/admin');
        }

        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        // Throttle aggressive guessing per user (5 wrong attempts → 15-min lockout)
        $rateKey = '2fa-email:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateKey, self::MAX_ATTEMPTS)) {
            return back()->withErrors([
                'code' => 'Trop de tentatives. Réessayez dans quelques minutes.',
            ]);
        }

        $codeMatches = $user->two_factor_email_code
            && Hash::check($request->input('code'), $user->two_factor_email_code);

        $expired = !$user->two_factor_email_code_expires_at
            || Carbon::parse($user->two_factor_email_code_expires_at)->isPast();

        if (!$codeMatches || $expired) {
            RateLimiter::hit($rateKey, 60 * 15); // 15-min window
            return back()->withErrors([
                'code' => $expired
                    ? 'Ce code a expiré. Cliquez sur "Renvoyer un code".'
                    : 'Code incorrect.',
            ]);
        }

        // Success — mark the session as 2FA-verified, clear the code.
        RateLimiter::clear($rateKey);
        $user->forceFill([
            'two_factor_email_code' => null,
            'two_factor_email_code_expires_at' => null,
            'two_factor_email_verified_at' => now(),
            'two_factor_email_attempts' => 0,
        ])->save();

        $request->session()->put('2fa_email_verified_at', now()->toIso8601String());

        return redirect()->intended('/admin');
    }

    /**
     * Re-send a fresh code (link on the verify page if user lost the email).
     */
    public function resend(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->two_factor_email_enabled) {
            return redirect('/admin');
        }

        $rateKey = '2fa-email-resend:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            return back()->withErrors([
                'code' => 'Trop de demandes de renvoi. Réessayez plus tard.',
            ]);
        }
        RateLimiter::hit($rateKey, 60 * 5);

        $this->sendCode($user);

        return back()->with('status', 'Un nouveau code vient de vous être envoyé par email.');
    }

    /**
     * Internal: generate a 6-digit code, store its hash on the user,
     * and email it.
     */
    private function sendCode(User $user): void
    {
        // Cryptographically secure 6-digit code
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'two_factor_email_code' => Hash::make($code),
            'two_factor_email_code_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ])->save();

        try {
            Mail::to($user->email)->send(new TwoFactorEmailCodeMail($user, $code, self::CODE_TTL_MINUTES));
        } catch (\Throwable $e) {
            // Don't expose mail errors to the user; log them.
            \Log::error('[2FA] Failed to send code email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
