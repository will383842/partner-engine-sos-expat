<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CsvImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CsvImportAdminController extends Controller
{
    /**
     * GET /api/admin/csv-imports — history of all CSV imports
     */
    public function index(Request $request): JsonResponse
    {
        $query = CsvImport::query();

        if ($request->filled('partner_id')) {
            $query->where('partner_firebase_id', $request->input('partner_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $imports = $query->orderByDesc('started_at')
            ->cursorPaginate($perPage);

        return response()->json($imports);
    }

    /**
     * GET /api/admin/csv-imports/{id} — import detail with errors
     */
    public function show(int $id): JsonResponse
    {
        $import = CsvImport::findOrFail($id);
        return response()->json($import);
    }
}
