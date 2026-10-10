<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Services\OfflineSaleSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfflineSaleSyncController extends Controller
{
    public function store(Request $request, OfflineSaleSyncService $sync): JsonResponse
    {
        abort_unless($request->user()->isOperator(), 403);
        $validated = $request->validate([
            'records' => ['required', 'array', 'max:100'],
            'records.*.client_uuid' => ['required', 'uuid'],
            'records.*.session_id' => ['required', 'integer', 'min:1'],
            'records.*.sale_type' => ['required', 'in:2d,3d'],
            'records.*.input' => ['required', 'string', 'max:255'],
            'records.*.recorded_at' => ['nullable', 'date'],
        ]);

        $results = [];
        foreach ($validated['records'] as $record) {
            $results[] = $sync->sync($request->user(), $record);
        }

        return response()->json(['results' => $results]);
    }
}
