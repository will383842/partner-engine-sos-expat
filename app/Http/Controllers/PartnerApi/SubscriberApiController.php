<?php

namespace App\Http\Controllers\PartnerApi;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Services\SubscriberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Server-to-server API for partners to automate subscriber provisioning.
 * Authentication via `partner.apikey` middleware.
 *
 * Endpoints (scope = subscribers:write or subscribers:read):
 *   POST   /api/v1/partner/subscribers           — create one
 *   POST   /api/v1/partner/subscribers/bulk      — create up to 500 at once
 *   GET    /api/v1/partner/subscribers           — list (paginated, filters)
 *   GET    /api/v1/partner/subscribers/{id}      — get one
 *   PATCH  /api/v1/partner/subscribers/{id}      — update
 *   DELETE /api/v1/partner/subscribers/{id}      — soft delete
 */
class SubscriberApiController extends Controller
{
    public function __construct(private SubscriberService $subscriberService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $partnerFirebaseId = (string) $request->attributes->get('partner_firebase_id');

        $query = Subscriber::where('partner_firebase_id', $partnerFirebaseId);
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($email = $request->query('email')) {
            $query->where('email', strtolower(trim($email)));
        }
        // Hierarchy filters — partners can narrow down to a cabinet / region / dept
        foreach (['group_label', 'region', 'department', 'external_id'] as $field) {
            if ($value = $request->query($field)) {
                $query->where($field, $value);
            }
        }

        $perPage = min((int) $request->query('per_page', 50), 500);
        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $partnerFirebaseId = (string) $request->attributes->get('partner_firebase_id');
        $subscriber = Subscriber::where('partner_firebase_id', $partnerFirebaseId)->find($id);
        if (!$subscriber) {
            return response()->json(['error' => 'not_found'], 404);
        }
        return response()->json(['data' => $subscriber]);
    }

    public function store(Request $request): JsonResponse
    {
        $partnerFirebaseId = (string) $request->attributes->get('partner_firebase_id');

        $validator = Validator::make($request->all(), $this->validationRules());
        if ($validator->fails()) {
            return response()->json(['error' => 'validation', 'details' => $validator->errors()], 422);
        }
        $data = $validator->validated();

        try {
            $subscriber = $this->subscriberService->create(
                $partnerFirebaseId,
                $data,
                'api_key:' . $this->getApiKeyId($request),
                'partner_api'
            );
            return response()->json(['data' => $subscriber], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'create_failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bulk create — up to 500 subscribers per call.
     * Returns summary + per-row result.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $partnerFirebaseId = (string) $request->attributes->get('partner_firebase_id');

        $payload = $request->input('subscribers', []);
        if (!is_array($payload) || count($payload) === 0) {
            return response()->json(['error' => 'empty_payload', 'message' => 'Provide a non-empty "subscribers" array.'], 422);
        }
        if (count($payload) > 500) {
            return response()->json(['error' => 'too_many', 'message' => 'Max 500 subscribers per bulk request.'], 422);
        }

        $rules = $this->validationRules();
        $results = [];
        $created = 0;
        $failed = 0;

        foreach ($payload as $idx => $row) {
            $validator = Validator::make($row, $rules);
            if ($validator->fails()) {
                $failed++;
                $results[] = [
                    'index' => $idx,
                    'email' => $row['email'] ?? null,
                    'status' => 'validation_failed',
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }
            $data = $validator->validated();
            try {
                $subscriber = $this->subscriberService->create(
                    $partnerFirebaseId,
                    $data,
                    'api_key:' . $this->getApiKeyId($request),
                    'partner_api_bulk'
                );
                $created++;
                $results[] = [
                    'index' => $idx,
                    'email' => $subscriber->email,
                    'status' => 'created',
                    'id' => $subscriber->id,
                    'sos_call_code' => $subscriber->sos_call_code,
                ];
            } catch (\Throwable $e) {
                $failed++;
                $results[] = [
                    'index' => $idx,
                    'email' => $row['email'] ?? null,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'summary' => [
                'total' => count($payload),
                'created' => $created,
                'failed' => $failed,
            ],
            'results' => $results,
        ], 207); // Multi-Status
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $partnerFirebaseId = (string) $request->attributes->get('partner_firebase_id');
        $subscriber = Subscriber::where('partner_firebase_id', $partnerFirebaseId)->find($id);
        if (!$subscriber) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|nullable|string|max:100',
            'last_name' => 'sometimes|nullable|string|max:100',
            'phone' => 'sometimes|nullable|string|max:50',
            'country' => 'sometimes|nullable|string|size:2',
            'language' => 'sometimes|string|max:5',
            'status' => 'sometimes|in:active,suspended,expired',
            'expires_at' => 'sometimes|nullable|date',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => 'validation', 'details' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        if (array_key_exists('expires_at', $data)) {
            $subscriber->sos_call_expires_at = $data['expires_at'];
            unset($data['expires_at']);
        }
        $subscriber->fill($data);
        $subscriber->save();

        return response()->json(['data' => $subscriber]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $partnerFirebaseId = (string) $request->attributes->get('partner_firebase_id');
        $subscriber = Subscriber::where('partner_firebase_id', $partnerFirebaseId)->find($id);
        if (!$subscriber) {
            return response()->json(['error' => 'not_found'], 404);
        }
        $subscriber->delete();
        return response()->json(['deleted' => true]);
    }

    protected function validationRules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'country' => 'nullable|string|size:2',
            'language' => 'sometimes|string|max:5',
            'tags' => 'nullable|array',
            'custom_fields' => 'nullable|array',
            'expires_at' => 'nullable|date|after:now',

            // Hierarchy (all optional — partner defines their own segmentation)
            'group_label' => 'nullable|string|max:120',
            'region' => 'nullable|string|max:120',
            'department' => 'nullable|string|max:120',
            'external_id' => 'nullable|string|max:255',
        ];
    }

    protected function getApiKeyId(Request $request): string
    {
        $key = $request->attributes->get('partner_api_key');
        return $key ? (string) $key->id : 'unknown';
    }
}
