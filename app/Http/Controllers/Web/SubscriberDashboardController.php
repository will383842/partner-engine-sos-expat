<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\SubscriberMagicLinkMail;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Subscriber dashboard at sos-call.sos-expat.com/mon-acces.
 *
 * Auth flow: magic link via email.
 *   1. User enters email on /mon-acces/login
 *   2. System creates single-use token (Redis TTL 15min)
 *   3. User clicks link in email: /mon-acces/auth?token=xyz
 *   4. System creates session + redirects to /mon-acces
 *   5. Session TTL 7 days, renewable on activity
 *
 * Anti-abuse:
 *   - Rate limit 3 magic links / email / hour
 *   - Rate limit 10 magic links / IP / hour
 *   - Token single-use (consumed on first use)
 */
class SubscriberDashboardController extends Controller
{
    private const MAGIC_LINK_TTL_SECONDS = 900; // 15 min
    private const SESSION_TTL_DAYS = 7;

    public function showLogin(Request $request)
    {
        // If already logged in, redirect to dashboard
        if ($request->session()->get('subscriber_id')) {
            return redirect()->route('subscriber.dashboard');
        }

        return view('subscriber.login', [
            'locale' => $request->session()->get('locale', 'fr'),
        ]);
    }

    public function sendMagicLink(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $email = strtolower(trim($validated['email']));
        $ip = $request->ip();

        // Rate limit per IP (10/hour)
        $ipKey = 'magic_link_ip:' . $ip;
        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            return back()->withErrors(['email' => __('Trop de tentatives. Réessayez plus tard.')]);
        }
        RateLimiter::hit($ipKey, 3600);

        // Rate limit per email (3/hour)
        $emailKey = 'magic_link_email:' . sha1($email);
        if (RateLimiter::tooManyAttempts($emailKey, 3)) {
            // Don't reveal rate limit: show generic success
            return view('subscriber.login_sent', ['email' => $email]);
        }
        RateLimiter::hit($emailKey, 3600);

        // Find subscriber (silently fail if not found — don't reveal existence)
        $subscriber = Subscriber::where('email', $email)->first();

        if ($subscriber && $subscriber->status !== 'suspended') {
            $token = Str::random(40);
            $ttl = self::MAGIC_LINK_TTL_SECONDS;

            try {
                Redis::setex(
                    "subscriber_magic_link:{$token}",
                    $ttl,
                    json_encode(['subscriber_id' => $subscriber->id, 'created_at' => now()->toIso8601String()])
                );

                $authUrl = url("/mon-acces/auth?token={$token}");

                Mail::to($email)->send(new SubscriberMagicLinkMail($subscriber, $authUrl));

                Log::info('[SubscriberDashboard] Magic link sent', [
                    'subscriber_id' => $subscriber->id,
                ]);
            } catch (\Throwable $e) {
                Log::error('[SubscriberDashboard] Magic link send failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return view('subscriber.login_sent', ['email' => $email]);
    }

    public function authenticate(Request $request)
    {
        $token = $request->query('token');

        if (!$token || strlen($token) !== 40) {
            return redirect()->route('subscriber.login')
                ->withErrors(['token' => 'Lien invalide ou expiré.']);
        }

        $key = "subscriber_magic_link:{$token}";

        try {
            $data = Redis::get($key);
        } catch (\Throwable $e) {
            Log::error('[SubscriberDashboard] Redis error', ['error' => $e->getMessage()]);
            return redirect()->route('subscriber.login')
                ->withErrors(['token' => 'Service temporairement indisponible.']);
        }

        if (!$data) {
            return redirect()->route('subscriber.login')
                ->withErrors(['token' => 'Lien invalide ou expiré.']);
        }

        $payload = json_decode($data, true);
        $subscriberId = $payload['subscriber_id'] ?? null;
        if (!$subscriberId) {
            return redirect()->route('subscriber.login')
                ->withErrors(['token' => 'Lien invalide.']);
        }

        // Consume token (single-use)
        Redis::del($key);

        $subscriber = Subscriber::find($subscriberId);
        if (!$subscriber || $subscriber->status === 'suspended') {
            return redirect()->route('subscriber.login')
                ->withErrors(['token' => 'Compte non disponible.']);
        }

        // Create session
        $request->session()->put('subscriber_id', $subscriber->id);
        $request->session()->put('subscriber_email', $subscriber->email);
        $request->session()->regenerate();

        Log::info('[SubscriberDashboard] Subscriber authenticated', [
            'subscriber_id' => $subscriber->id,
        ]);

        return redirect()->route('subscriber.dashboard');
    }

    public function dashboard(Request $request)
    {
        $subscriberId = $request->session()->get('subscriber_id');
        if (!$subscriberId) {
            return redirect()->route('subscriber.login');
        }

        $subscriber = Subscriber::with('agreement')->find($subscriberId);
        if (!$subscriber) {
            $request->session()->flush();
            return redirect()->route('subscriber.login');
        }

        // Last 5 call activities
        $recentCalls = SubscriberActivity::where('subscriber_id', $subscriber->id)
            ->where('type', 'call_completed')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $totalCalls = $subscriber->calls_expert + $subscriber->calls_lawyer;
        $maxCalls = $subscriber->agreement->max_calls_per_subscriber ?? 0;

        return view('subscriber.dashboard', [
            'subscriber' => $subscriber,
            'agreement' => $subscriber->agreement,
            'recentCalls' => $recentCalls,
            'totalCalls' => $totalCalls,
            'maxCalls' => $maxCalls,
            'sosCallUrl' => url('/sos-call?code=' . urlencode($subscriber->sos_call_code ?? '')),
        ]);
    }

    public function logout(Request $request)
    {
        $request->session()->flush();
        $request->session()->regenerate();
        return redirect()->route('subscriber.login');
    }
}
