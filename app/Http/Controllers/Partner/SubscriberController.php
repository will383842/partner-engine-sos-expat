<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function destroy(int $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function import(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function export(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function resendInvitation(int $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }
}
