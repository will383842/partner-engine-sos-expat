<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAgreementRequest;
use App\Models\Agreement;
use App\Services\AgreementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgreementAdminController extends Controller
{
    public function __construct(protected AgreementService $agreementService)
    {
    }

    /**
     * POST /api/admin/partners/{id}/agreements
     */
    public function store(CreateAgreementRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        $data['partner_name'] = $request->input('partner_name');

        $agreement = $this->agreementService->create(
            partnerFirebaseId: $id,
            data: $data,
            actorId: $request->attributes->get('firebase_uid'),
            actorRole: 'admin',
            ip: $request->ip(),
        );

        return response()->json($agreement, 201);
    }

    /**
     * GET /api/admin/partners/{id}/agreements/{agreementId}
     */
    public function show(string $id, int $agreementId): JsonResponse
    {
        $agreement = Agreement::where('partner_firebase_id', $id)
            ->findOrFail($agreementId);

        $agreement->loadCount('subscribers');

        return response()->json($agreement);
    }

    /**
     * PUT /api/admin/partners/{id}/agreements/{agreementId}
     */
    public function update(Request $request, string $id, int $agreementId): JsonResponse
    {
        $agreement = Agreement::where('partner_firebase_id', $id)
            ->findOrFail($agreementId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:draft,active,paused,expired',
            'discount_type' => 'sometimes|in:none,fixed,percent',
            'discount_value' => 'sometimes|integer|min:0',
            'discount_max_cents' => 'nullable|integer|min:0',
            'discount_label' => 'nullable|string|max:255',
            'commission_per_call_lawyer' => 'sometimes|integer|min:0',
            'commission_per_call_expat' => 'sometimes|integer|min:0',
            'commission_type' => 'sometimes|in:fixed,percent',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'max_subscribers' => 'nullable|integer|min:1',
            'max_calls_per_subscriber' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $agreement = $this->agreementService->update(
            agreement: $agreement,
            data: $validated,
            actorId: $request->attributes->get('firebase_uid'),
            actorRole: 'admin',
            ip: $request->ip(),
        );

        return response()->json($agreement);
    }

    /**
     * DELETE /api/admin/partners/{id}/agreements/{agreementId}
     */
    public function destroy(Request $request, string $id, int $agreementId): JsonResponse
    {
        $agreement = Agreement::where('partner_firebase_id', $id)
            ->findOrFail($agreementId);

        $this->agreementService->delete(
            agreement: $agreement,
            actorId: $request->attributes->get('firebase_uid'),
            actorRole: 'admin',
            ip: $request->ip(),
        );

        return response()->json(['message' => 'Agreement deleted']);
    }

    /**
     * POST /api/admin/partners/{id}/agreements/{agreementId}/renew
     */
    public function renew(Request $request, string $id, int $agreementId): JsonResponse
    {
        $agreement = Agreement::where('partner_firebase_id', $id)
            ->findOrFail($agreementId);

        $overrides = $request->validate([
            'name' => 'nullable|string|max:255',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'status' => 'sometimes|in:draft,active',
        ]);

        $newAgreement = $this->agreementService->renew(
            agreement: $agreement,
            overrides: $overrides,
            actorId: $request->attributes->get('firebase_uid'),
            actorRole: 'admin',
            ip: $request->ip(),
        );

        return response()->json($newAgreement, 201);
    }
}
