<?php

namespace App\Http\Middleware;

use App\Models\PartnerApiKey as PartnerApiKeyModel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates requests using a partner server-to-server API key.
 *
 * Accepts either:
 *   - Authorization: Bearer pk_live_xxxxxxxxxxxx
 *   - X-Partner-API-Key: pk_live_xxxxxxxxxxxx
 *
 * On success, injects the partner API key + partner_firebase_id into the
 * request attributes for downstream controllers to use:
 *   - $request->attributes->get('partner_api_key')       → PartnerApiKey model
 *   - $request->attributes->get('partner_firebase_id')   → string
 *   - $request->attributes->set('auth_method', 'api_key')
 *
 * This middleware replaces the `firebase.auth + require.partner` pair for
 * routes that should be accessible via server-to-server automation.
 */
class PartnerApiKey
{
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return response()->json([
                'error' => 'missing_api_key',
                'message' => 'Provide your API key via the Authorization: Bearer header or X-Partner-API-Key.',
            ], 401);
        }

        $apiKey = PartnerApiKeyModel::findByPlainToken($token);

        if (!$apiKey) {
            return response()->json([
                'error' => 'invalid_api_key',
                'message' => 'The provided API key is invalid or has been revoked.',
            ], 401);
        }

        if ($requiredScope && !$apiKey->hasScope($requiredScope)) {
            return response()->json([
                'error' => 'insufficient_scope',
                'message' => "This API key does not have the '{$requiredScope}' scope.",
                'required_scope' => $requiredScope,
                'your_scopes' => $apiKey->scopes,
            ], 403);
        }

        // Record usage (non-blocking style — even if DB hiccups, we still serve the request)
        try {
            $apiKey->recordUsage($request->ip());
        } catch (\Throwable $e) {
            // Intentionally swallow to avoid blocking legitimate traffic on telemetry.
        }

        $request->attributes->set('partner_api_key', $apiKey);
        $request->attributes->set('partner_firebase_id', $apiKey->partner_firebase_id);
        $request->attributes->set('auth_method', 'api_key');

        return $next($request);
    }

    protected function extractToken(Request $request): ?string
    {
        $auth = $request->header('Authorization', '');
        if (is_string($auth) && preg_match('/^Bearer\s+(pk_(?:live|test)_[A-Za-z0-9]+)$/', $auth, $m)) {
            return $m[1];
        }
        $xKey = $request->header('X-Partner-API-Key', '');
        if (is_string($xKey) && str_starts_with($xKey, 'pk_')) {
            return trim($xKey);
        }
        return null;
    }
}
