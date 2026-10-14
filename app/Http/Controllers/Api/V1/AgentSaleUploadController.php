<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AgentSession;
use App\Services\OfflineSaleSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentSaleUploadController extends AgentApiController
{
    /**
     * Auto-upload endpoint: one record for a live entry, many records for a
     * backlog drained from the phone. client_uuid makes every retry harmless.
     */
    public function upload(Request $request, OfflineSaleSyncService $sync): JsonResponse
    {
        $agent = $this->agent($request);

        $validated = $request->validate([
            'records' => ['required', 'array', 'min:1', 'max:100'],
            'records.*.client_uuid' => ['required', 'uuid'],
            'records.*.session_id' => ['required', 'integer', 'min:1'],
            'records.*.sale_type' => ['required', 'in:2d,3d'],
            'records.*.input' => ['required', 'string', 'max:255'],
            'records.*.recorded_at' => ['nullable', 'date'],
        ]);

        $sessions = AgentSession::query()
            ->whereIn('id', collect($validated['records'])->pluck('session_id')->unique()->all())
            ->where('agent_id', $agent->id)
            ->get()
            ->keyBy('id');

        $results = [];

        foreach ($validated['records'] as $record) {
            $session = $sessions->get($record['session_id']);
            $handler = $session?->operator;

            if (! $session || ! $handler || ! $handler->status || ! $handler->isHandler()) {
                $results[] = [
                    'client_uuid' => $record['client_uuid'],
                    'status' => 'error',
                    'message' => 'This Agent session is not available to your account.',
                ];
            } else {
                $results[] = $sync->sync($handler, $record, 'phone');
            }
        }

        return response()->json([
            'results' => $results,
            'uploaded_at' => now()->toIso8601String(),
        ]);
    }
}
