<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * SOS-Call controller — 3 public/webhook endpoints:
 *
 *   POST /sos-call/check            (public, rate-limited)     — Verify code or phone+email eligibility
 *   POST /sos-call/check-session    (webhook.secret)           — Validate session token from Firebase
 *   POST /sos-call/log              (webhook.secret)           — Log a completed SOS-Call call
 *
 * Flow:
 *   1. Client visits sos-call.sos-expat.com
 *   2. Enters SOS-Call code (or phone+email fallback)
 *   3. Frontend calls POST /sos-call/check → receives session_token if eligible
 *   4. Client picks call type (lawyer/expat)
 *   5. Frontend calls Firebase createAndScheduleCall with sosCallSessionToken
 *   6. Firebase verifies token via POST /sos-call/check-session
 *   7. Firebase triggers Twilio call (free — bypass Stripe)
 *   8. Firebase calls POST /sos-call/log → increments counters
 *
 * Security:
 *   - Rate limit per IP (10/min) + per phone (5/15min)
 *   - Anti-brute-force: max 3 failed attempts per phone → 10min block
 *   - Session tokens are single-use (invalidated after first call)
 *   - TTL: OTP/attempts 10min, session 15min
 */
class SosCallController extends Controller
{
    public const SESSION_TTL_SECONDS = 900;   // 15 min
    public const ATTEMPTS_TTL_SECONDS = 900;  // 15 min
    public const BLOCK_TTL_SECONDS = 600;     // 10 min
    public const MAX_ATTEMPTS = 3;

    public function __construct(protected AuditService $audit) {}

    /**
     * POST /sos-call/check
     *
     * Body: { code?: string } OR { phone: string, email: string }
     *
     * Returns JSON with status:
     *   - access_granted   → { session_token, partner_name, call_types_allowed, calls_remaining }
     *   - code_invalid     → wrong code (with attempts_remaining)
     *   - phone_match_email_mismatch → { partner_name, attempts_remaining }
     *   - not_found
     *   - quota_reached    → subscriber has used all allowed calls
     *   - expired          → subscriber sos_call_expires_at is past
     *   - rate_limited     → too many attempts
     *   - agreement_inactive → SOS-Call disabled on this agreement
     */
    public function check(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|max:30',
            'phone' => 'sometimes|string|max:50',
            'email' => 'sometimes|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'invalid_input', 'errors' => $validator->errors()], 422);
        }

        $code = trim((string) $request->input('code', ''));
        $phone = $this->normalizePhone($request->input('phone', ''));
        $email = strtolower(trim((string) $request->input('email', '')));

        // Must provide either a code OR both phone+email
        $hasCode = !empty($code);
        $hasPhoneEmail = !empty($phone) && !empty($email);

        if (!$hasCode && !$hasPhoneEmail) {
            return response()->json([
                'status' => 'invalid_input',
                'message' => 'Provide either a code or phone+email',
            ], 422);
        }

        // Rate-limit key (use code OR phone as identifier)
        $rlKey = $hasCode ? "code:{$code}" : "phone:{$phone}";

        // Check if this identifier is blocked
        if (Cache::get("sos_call:block:{$rlKey}")) {
            $this->audit->log('system', 'system', 'sos_call.rate_limited', null, null, [
                'identifier' => $this->maskIdentifier($rlKey),
                'ip' => $request->ip(),
            ], $request->ip());
            return response()->json(['status' => 'rate_limited'], 429);
        }

        // Try to find the subscriber
        $subscriber = null;
        $emailMismatch = false;

        if ($hasCode) {
            $subscriber = Subscriber::with('agreement')
                ->where('sos_call_code', $code)
                ->first();
        } else {
            // Fallback: phone + email match
            $subByPhone = Subscriber::with('agreement')
                ->where('phone', $phone)
                ->whereNull('deleted_at')
                ->first();

            if ($subByPhone && strtolower($subByPhone->email) === $email) {
                $subscriber = $subByPhone;
            } elseif ($subByPhone) {
                // Phone matches but email doesn't — informative response
                $emailMismatch = true;
                $this->incrementAttempts($rlKey, $request->ip());
                return response()->json([
                    'status' => 'phone_match_email_mismatch',
                    'partner_name' => $subByPhone->agreement?->partner_name,
                    'attempts_remaining' => max(0, self::MAX_ATTEMPTS - $this->getAttempts($rlKey)),
                ]);
            }
        }

        // No subscriber found
        if (!$subscriber) {
            $this->incrementAttempts($rlKey, $request->ip());
            $this->audit->log('system', 'system', 'sos_call.not_found', null, null, [
                'identifier' => $this->maskIdentifier($rlKey),
                'ip' => $request->ip(),
            ], $request->ip());
            return response()->json([
                'status' => 'not_found',
                'attempts_remaining' => max(0, self::MAX_ATTEMPTS - $this->getAttempts($rlKey)),
            ]);
        }

        // Subscriber found — verify agreement
        $agreement = $subscriber->agreement;
        if (!$agreement || !$agreement->sos_call_active || $agreement->status !== 'active') {
            return response()->json(['status' => 'agreement_inactive']);
        }

        // Verify subscriber status
        if ($subscriber->status !== 'active') {
            return response()->json(['status' => 'subscriber_' . $subscriber->status]);
        }

        // Verify not expired
        if ($subscriber->sos_call_expires_at && $subscriber->sos_call_expires_at->isPast()) {
            return response()->json(['status' => 'expired']);
        }

        // Verify quota not reached
        if ($subscriber->hasReachedSosCallQuota()) {
            return response()->json(['status' => 'quota_reached']);
        }

        // All checks passed — create session token
        $sessionToken = bin2hex(random_bytes(16)); // 32 hex chars

        Cache::put("sos_call:session:{$sessionToken}", [
            'subscriber_id' => $subscriber->id,
            'partner_firebase_id' => $subscriber->partner_firebase_id,
            'agreement_id' => $subscriber->agreement_id,
            'partner_name' => $agreement->partner_name,
            'first_name' => $subscriber->first_name,
            'call_types_allowed' => $agreement->call_types_allowed,
            'used' => false,
            'created_at' => now()->timestamp,
        ], self::SESSION_TTL_SECONDS);

        // Clear attempts counter on success
        Cache::forget("sos_call:attempts:{$rlKey}");

        $this->audit->log('system', 'system', 'sos_call.access_granted', 'subscriber', $subscriber->id, [
            'via' => $hasCode ? 'code' : 'phone_email',
        ], $request->ip());

        return response()->json([
            'status' => 'access_granted',
            'session_token' => $sessionToken,
            'partner_name' => $agreement->partner_name,
            'call_types_allowed' => $agreement->call_types_allowed,
            'calls_remaining' => $subscriber->sos_call_remaining,
            'expires_at' => $subscriber->sos_call_expires_at?->toIso8601String(),
            'first_name' => $subscriber->first_name,
        ]);
    }

    /**
     * POST /sos-call/check-session
     *
     * Called by Firebase Functions (createAndScheduleCallFunction) to verify a session token
     * before triggering a free SOS-Call.
     *
     * Body: { session_token: string, call_type: 'lawyer' | 'expat' }
     *
     * Returns: { valid: bool, subscriber_id?, partner_firebase_id?, agreement_id?,
     *            partner_name?, client_first_name?, reason? }
     */
    public function checkSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_token' => 'required|string|size:32',
            'call_type' => 'required|in:lawyer,expat',
        ]);

        if ($validator->fails()) {
            return response()->json(['valid' => false, 'reason' => 'invalid_input'], 422);
        }

        $token = $request->input('session_token');
        $callType = $request->input('call_type');

        $session = Cache::get("sos_call:session:{$token}");

        if (!$session) {
            return response()->json(['valid' => false, 'reason' => 'session_not_found_or_expired']);
        }

        if ($session['used']) {
            return response()->json(['valid' => false, 'reason' => 'session_already_used']);
        }

        // Verify call_type is allowed by agreement
        $allowed = $session['call_types_allowed'];
        if ($allowed === 'expat_only' && $callType !== 'expat') {
            return response()->json(['valid' => false, 'reason' => 'call_type_not_allowed']);
        }
        if ($allowed === 'lawyer_only' && $callType !== 'lawyer') {
            return response()->json(['valid' => false, 'reason' => 'call_type_not_allowed']);
        }

        // Don't consume the session here — consume it in /log after call actually created
        return response()->json([
            'valid' => true,
            'subscriber_id' => $session['subscriber_id'],
            'partner_firebase_id' => $session['partner_firebase_id'],
            'agreement_id' => $session['agreement_id'],
            'partner_name' => $session['partner_name'] ?? null,
            'client_first_name' => $session['first_name'] ?? null,
        ]);
    }

    /**
     * POST /sos-call/log
     *
     * Called by Firebase (createAndScheduleCallFunction) AFTER the call is successfully scheduled.
     * Atomically:
     *   - Marks the session token as used (single-use enforcement)
     *   - Increments calls_expert or calls_lawyer counter on subscriber
     *   - Creates a SubscriberActivity row with is_sos_call=true
     *
     * Body: { session_token, call_session_id, call_type, duration_seconds? }
     */
    public function log(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_token' => 'required|string|size:32',
            'call_session_id' => 'required|string|max:128',
            'call_type' => 'required|in:lawyer,expat',
            'duration_seconds' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'reason' => 'invalid_input'], 422);
        }

        $token = $request->input('session_token');
        $session = Cache::get("sos_call:session:{$token}");

        if (!$session || $session['used']) {
            return response()->json(['success' => false, 'reason' => 'session_not_found_or_used'], 404);
        }

        $callType = $request->input('call_type');
        $callSessionId = $request->input('call_session_id');
        $duration = (int) $request->input('duration_seconds', 0);

        try {
            DB::transaction(function () use ($session, $callType, $callSessionId, $duration, $token) {
                $subscriber = Subscriber::where('id', $session['subscriber_id'])->lockForUpdate()->first();

                if (!$subscriber) {
                    throw new \RuntimeException("Subscriber {$session['subscriber_id']} not found");
                }

                // Increment per-type counter
                if ($callType === 'expat') {
                    $subscriber->increment('calls_expert');
                } else {
                    $subscriber->increment('calls_lawyer');
                }
                $subscriber->increment('total_calls');
                $subscriber->last_activity_at = now();
                $subscriber->save();

                // Log activity (unique call_session_id prevents double-logging)
                SubscriberActivity::updateOrCreate(
                    ['call_session_id' => $callSessionId],
                    [
                        'subscriber_id' => $subscriber->id,
                        'partner_firebase_id' => $subscriber->partner_firebase_id,
                        'type' => 'call_completed',
                        'provider_type' => $callType,
                        'call_duration_seconds' => $duration,
                        'amount_paid_cents' => 0, // free for client
                        'discount_applied_cents' => $callType === 'lawyer' ? 4900 : 1900,
                        'commission_earned_cents' => 0, // no commission in SOS-Call model
                        'metadata' => ['is_sos_call' => true, 'sos_call_code' => $subscriber->sos_call_code],
                    ]
                );

                // Invalidate session (single-use)
                Cache::put("sos_call:session:{$token}", array_merge($session, ['used' => true]), self::SESSION_TTL_SECONDS);
            });

            $this->audit->log('system', 'system', 'sos_call.call_logged', 'subscriber', $session['subscriber_id'], [
                'call_session_id' => $callSessionId,
                'call_type' => $callType,
                'duration_seconds' => $duration,
            ], $request->ip());

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('SOS-Call log failed', [
                'session_token' => substr($token, 0, 8) . '...',
                'call_session_id' => $callSessionId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'reason' => 'internal_error'], 500);
        }
    }

    // --- Helpers ---

    /**
     * Normalize phone to E.164 format (expected from frontend).
     * We don't re-normalize heavily — we trust the frontend (IntlPhoneInput).
     * Just basic cleanup.
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', $phone);
        if ($phone && !Str::startsWith($phone, '+')) {
            // If no + prefix, assume invalid (frontend should always send E.164)
            return '';
        }
        // Basic E.164 validation
        if (!preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
            return '';
        }
        return $phone;
    }

    /**
     * Increment attempts counter for a given identifier (code or phone).
     * If threshold exceeded, set a block.
     */
    protected function incrementAttempts(string $rlKey, string $ip): int
    {
        $key = "sos_call:attempts:{$rlKey}";
        $attempts = Cache::increment($key);
        if ($attempts === 1) {
            Cache::put($key, 1, self::ATTEMPTS_TTL_SECONDS);
        }

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::put("sos_call:block:{$rlKey}", true, self::BLOCK_TTL_SECONDS);
            Cache::forget($key);

            $this->audit->log('system', 'system', 'sos_call.blocked', null, null, [
                'identifier' => $this->maskIdentifier($rlKey),
                'ip' => $ip,
                'attempts' => $attempts,
            ], $ip);
        }

        return (int) $attempts;
    }

    protected function getAttempts(string $rlKey): int
    {
        return (int) (Cache::get("sos_call:attempts:{$rlKey}") ?? 0);
    }

    /**
     * Mask identifier for logs (never log full codes or phone numbers).
     */
    protected function maskIdentifier(string $rlKey): string
    {
        if (Str::startsWith($rlKey, 'code:')) {
            return 'code:' . substr($rlKey, 5, 3) . '...';
        }
        if (Str::startsWith($rlKey, 'phone:')) {
            $phone = substr($rlKey, 6);
            return 'phone:' . substr($phone, 0, 4) . '****' . substr($phone, -2);
        }
        return $rlKey;
    }
}
