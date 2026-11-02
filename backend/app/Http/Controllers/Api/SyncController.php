<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    public function upload(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:64'],
            'operations' => ['required', 'array', 'min:1', 'max:50'],
            'operations.*.operation_id' => ['required', 'string', 'max:64'],
            'operations.*.operation_type' => ['required', 'string', 'in:checkout,stock_movement,customer_create,collection'],
            'operations.*.payload_version' => ['required', 'integer', 'min:1'],
            'operations.*.client_time' => ['required', 'date'],
            'operations.*.idempotency_key' => ['required', 'string', 'max:100'],
            'operations.*.payload' => ['required', 'array'],
        ]);

        $user = $request->user();
        $results = [];

        foreach ($data['operations'] as $operation) {
            $existing = SyncOperation::where('branch_id', $branch->id)
                ->where('operation_id', $operation['operation_id'])
                ->first();

            if ($existing) {
                $results[] = [
                    'operation_id' => $operation['operation_id'],
                    'status' => $existing->status,
                    'result' => $existing->result,
                    'error' => $existing->error_message,
                    'replayed' => true,
                ];
                continue;
            }

            $syncOp = SyncOperation::create([
                'branch_id' => $branch->id,
                'device_id' => $data['device_id'],
                'actor_id' => $user->id,
                'operation_type' => $operation['operation_type'],
                'operation_id' => $operation['operation_id'],
                'payload_version' => $operation['payload_version'],
                'idempotency_key' => $operation['idempotency_key'],
                'status' => 'accepted',
                'client_time' => $operation['client_time'],
                'server_time' => now(),
                'payload' => $operation['payload'],
                'result' => ['message' => 'Operation accepted for processing'],
            ]);

            $results[] = [
                'operation_id' => $operation['operation_id'],
                'status' => 'accepted',
                'result' => $syncOp->result,
                'replayed' => false,
            ];
        }

        return response()->json([
            'data' => [
                'device_id' => $data['device_id'],
                'processed' => count($results),
                'results' => $results,
            ],
        ]);
    }

    public function status(Request $request, Branch $branch): JsonResponse
    {
        $operations = SyncOperation::where('branch_id', $branch->id)
            ->where('device_id', $request->query('device_id'))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $operations->map(fn (SyncOperation $op) => [
                'operation_id' => $op->operation_id,
                'operation_type' => $op->operation_type,
                'status' => $op->status,
                'client_time' => $op->client_time?->toIso8601String(),
                'server_time' => $op->server_time?->toIso8601String(),
                'attempt_count' => $op->attempt_count,
                'error' => $op->error_message,
                'created_at' => $op->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
