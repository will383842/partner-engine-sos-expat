<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgreementAdminController extends Controller
{
    public function store(Request $request, string $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function show(string $id, int $agreementId): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function update(Request $request, string $id, int $agreementId): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function destroy(string $id, int $agreementId): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function renew(string $id, int $agreementId): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }
}
