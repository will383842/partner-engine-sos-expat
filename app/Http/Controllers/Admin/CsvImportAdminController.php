<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CsvImportAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented yet'], 501);
    }
}
