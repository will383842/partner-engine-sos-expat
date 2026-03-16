<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriberAdminController extends Controller
{
    public function index(string $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function update(Request $request, string $id, int $subscriberId): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function suspend(string $id, int $subscriberId): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function reactivate(string $id, int $subscriberId): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function import(Request $request, string $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function bulkDestroy(Request $request, string $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }
}
