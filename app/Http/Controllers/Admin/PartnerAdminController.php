<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function emailTemplates(string $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function updateEmailTemplate(Request $request, string $id, string $type): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function deleteEmailTemplate(string $id, string $type): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function auditLog(string $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }
}
