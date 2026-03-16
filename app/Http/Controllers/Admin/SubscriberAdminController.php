<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportCsvRequest;
use App\Models\CsvImport;
use App\Models\Subscriber;
use App\Jobs\ProcessCsvImport;
use App\Services\SubscriberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriberAdminController extends Controller
{
    public function __construct(protected SubscriberService $subscriberService)
    {
    }

    /**
     * GET /api/admin/partners/{id}/subscribers
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $query = Subscriber::where('partner_firebase_id', $id)
            ->whereNull('deleted_at');

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

        $perPage = min((int) $request->input('per_page', 20), 100);
        $subscribers = $query->orderByDesc('created_at')
            ->cursorPaginate($perPage);

        return response()->json($subscribers);
    }

    /**
     * PUT /api/admin/partners/{id}/subscribers/{subscriberId}
     */
    public function update(Request $request, string $id, int $subscriberId): JsonResponse
    {
        $subscriber = Subscriber::where('partner_firebase_id', $id)
            ->findOrFail($subscriberId);

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'country' => 'nullable|string|size:2',
            'language' => 'sometimes|string|max:5',
            'tags' => 'nullable|array',
            'custom_fields' => 'nullable|array',
            'status' => 'sometimes|in:invited,registered,active,suspended,expired',
        ]);

        $subscriber = $this->subscriberService->update(
            subscriber: $subscriber,
            data: $validated,
            actorId: $request->attributes->get('firebase_uid'),
            actorRole: 'admin',
            ip: $request->ip(),
        );

        return response()->json($subscriber);
    }

    /**
     * POST /api/admin/partners/{id}/subscribers/{subscriberId}/suspend
     */
    public function suspend(Request $request, string $id, int $subscriberId): JsonResponse
    {
        $subscriber = Subscriber::where('partner_firebase_id', $id)
            ->findOrFail($subscriberId);

        $subscriber = $this->subscriberService->suspend(
            subscriber: $subscriber,
            actorId: $request->attributes->get('firebase_uid'),
            actorRole: 'admin',
            ip: $request->ip(),
        );

        return response()->json($subscriber);
    }

    /**
     * POST /api/admin/partners/{id}/subscribers/{subscriberId}/reactivate
     */
    public function reactivate(Request $request, string $id, int $subscriberId): JsonResponse
    {
        $subscriber = Subscriber::where('partner_firebase_id', $id)
            ->findOrFail($subscriberId);

        $subscriber = $this->subscriberService->reactivate(
            subscriber: $subscriber,
            actorId: $request->attributes->get('firebase_uid'),
            actorRole: 'admin',
            ip: $request->ip(),
        );

        return response()->json($subscriber);
    }

    /**
     * POST /api/admin/partners/{id}/subscribers/import
     */
    public function import(ImportCsvRequest $request, string $id): JsonResponse
    {
        $file = $request->file('file');
        $path = $file->store('csv-imports', 'local');

        $csvImport = CsvImport::create([
            'partner_firebase_id' => $id,
            'uploaded_by' => $request->attributes->get('firebase_uid'),
            'filename' => $file->getClientOriginalName(),
            'status' => 'processing',
            'started_at' => now(),
        ]);

        ProcessCsvImport::dispatch(
            csvImportId: $csvImport->id,
            partnerFirebaseId: $id,
            filePath: $path,
        );

        return response()->json([
            'message' => 'Import started',
            'import_id' => $csvImport->id,
        ], 200);
    }

    /**
     * DELETE /api/admin/partners/{id}/subscribers/bulk
     */
    public function bulkDestroy(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'subscriber_ids' => 'required|array|min:1',
            'subscriber_ids.*' => 'integer',
        ]);

        $deleted = Subscriber::where('partner_firebase_id', $id)
            ->whereIn('id', $validated['subscriber_ids'])
            ->whereNull('deleted_at')
            ->get();

        foreach ($deleted as $subscriber) {
            $this->subscriberService->delete(
                subscriber: $subscriber,
                actorId: $request->attributes->get('firebase_uid'),
                actorRole: 'admin',
                ip: $request->ip(),
            );
        }

        return response()->json([
            'message' => "Deleted {$deleted->count()} subscribers",
            'count' => $deleted->count(),
        ]);
    }
}
