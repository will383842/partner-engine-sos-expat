<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSubscriberRequest;
use App\Http\Requests\ImportCsvRequest;
use App\Models\CsvImport;
use App\Models\Subscriber;
use App\Jobs\ProcessCsvImport;
use App\Services\SubscriberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function __construct(protected SubscriberService $subscriberService)
    {
    }

    /**
     * GET /api/partner/subscribers — paginated list with filters
     */
    public function index(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');

        $query = Subscriber::where('partner_firebase_id', $partnerId)
            ->whereNull('deleted_at');

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('email', 'ilike', "%{$search}%")
                  ->orWhere('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('tags')) {
            $tags = (array) $request->input('tags');
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        // Cursor-based pagination
        $perPage = min((int) $request->input('per_page', 20), 100);
        $subscribers = $query->orderByDesc('created_at')
            ->cursorPaginate($perPage);

        return response()->json($subscribers);
    }

    /**
     * POST /api/partner/subscribers — manual add
     */
    public function store(CreateSubscriberRequest $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');

        // Check for duplicate
        $exists = Subscriber::where('partner_firebase_id', $partnerId)
            ->where('email', $request->input('email'))
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Subscriber with this email already exists for this partner'], 409);
        }

        try {
            $subscriber = $this->subscriberService->create(
                partnerFirebaseId: $partnerId,
                data: $request->validated(),
                actorId: $request->attributes->get('firebase_uid'),
                actorRole: 'partner',
                ip: $request->ip(),
            );

            return response()->json($subscriber, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/partner/subscribers/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');

        $subscriber = Subscriber::where('partner_firebase_id', $partnerId)
            ->with(['activities' => function ($q) {
                $q->orderByDesc('created_at')->limit(20);
            }])
            ->findOrFail($id);

        return response()->json($subscriber);
    }

    /**
     * PUT /api/partner/subscribers/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');

        $subscriber = Subscriber::where('partner_firebase_id', $partnerId)
            ->findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'country' => 'nullable|string|size:2',
            'language' => 'sometimes|string|max:5',
            'tags' => 'nullable|array',
            'custom_fields' => 'nullable|array',
        ]);

        $subscriber = $this->subscriberService->update(
            subscriber: $subscriber,
            data: $validated,
            actorId: $request->attributes->get('firebase_uid'),
            actorRole: 'partner',
            ip: $request->ip(),
        );

        return response()->json($subscriber);
    }

    /**
     * DELETE /api/partner/subscribers/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');

        $subscriber = Subscriber::where('partner_firebase_id', $partnerId)
            ->findOrFail($id);

        $this->subscriberService->delete(
            subscriber: $subscriber,
            actorId: $request->attributes->get('firebase_uid'),
            actorRole: 'partner',
            ip: $request->ip(),
        );

        return response()->json(['message' => 'Subscriber deleted']);
    }

    /**
     * POST /api/partner/subscribers/import — CSV upload
     */
    public function import(ImportCsvRequest $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');
        $file = $request->file('file');

        // Store file temporarily
        $path = $file->store('csv-imports', 'local');

        // Create import record
        $csvImport = CsvImport::create([
            'partner_firebase_id' => $partnerId,
            'uploaded_by' => $request->attributes->get('firebase_uid'),
            'filename' => $file->getClientOriginalName(),
            'status' => 'processing',
            'started_at' => now(),
        ]);

        // Dispatch background job
        ProcessCsvImport::dispatch(
            csvImportId: $csvImport->id,
            partnerFirebaseId: $partnerId,
            filePath: $path,
        );

        return response()->json([
            'message' => 'Import started',
            'import_id' => $csvImport->id,
        ], 200);
    }

    /**
     * GET /api/partner/subscribers/export — CSV export
     */
    public function export(Request $request): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');

        $subscribers = Subscriber::where('partner_firebase_id', $partnerId)
            ->whereNull('deleted_at')
            ->get(['email', 'first_name', 'last_name', 'phone', 'country', 'language', 'status', 'total_calls', 'total_spent_cents', 'created_at']);

        // Return as JSON — frontend can convert to CSV
        return response()->json([
            'count' => $subscribers->count(),
            'data' => $subscribers,
        ]);
    }

    /**
     * POST /api/partner/subscribers/{id}/resend-invitation
     */
    public function resendInvitation(Request $request, int $id): JsonResponse
    {
        $partnerId = $request->attributes->get('partner_firebase_id');

        $subscriber = Subscriber::where('partner_firebase_id', $partnerId)
            ->findOrFail($id);

        try {
            $this->subscriberService->resendInvitation(
                subscriber: $subscriber,
                actorId: $request->attributes->get('firebase_uid'),
                actorRole: 'partner',
                ip: $request->ip(),
            );

            return response()->json(['message' => 'Invitation resent']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
