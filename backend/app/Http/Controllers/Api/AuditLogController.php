<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function branchIndex(Request $request, Branch $branch): JsonResponse
    {
        return $this->index($request, $branch->id);
    }

    public function platformIndex(Request $request): JsonResponse
    {
        return $this->index($request, null);
    }

    private function index(Request $request, ?string $branchId): JsonResponse
    {
        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'event' => ['sometimes', 'string', 'max:150'],
            'actor_id' => ['sometimes', 'string'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->when($branchId !== null, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->when($branchId === null, fn (Builder $query): Builder => $query->whereNull('branch_id'))
            ->when(isset($data['event']) && $data['event'] !== '', fn (Builder $query): Builder => $query->where('action', $data['event']))
            ->when(isset($data['actor_id']) && $data['actor_id'] !== '', fn (Builder $query): Builder => $query->where('user_id', $data['actor_id']))
            ->when(isset($data['from']), fn (Builder $query): Builder => $query->where('created_at', '>=', CarbonImmutable::parse($data['from'])->startOfDay()))
            ->when(isset($data['to']), fn (Builder $query): Builder => $query->where('created_at', '<=', CarbonImmutable::parse($data['to'])->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 20))
            ->withQueryString();

        $users = User::query()
            ->whereIn('id', $logs->getCollection()->pluck('user_id')->filter()->values()->all())
            ->pluck('name', 'id');
        $branches = Branch::query()
            ->whereIn('id', $logs->getCollection()->pluck('branch_id')->filter()->values()->all())
            ->pluck('name', 'id');

        return response()->json([
            'data' => $logs->getCollection()->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'event' => $log->action,
                'actor' => isset($users[$log->user_id])
                    ? ['id' => $log->user_id, 'name' => $users[$log->user_id]]
                    : ($log->user_id !== null ? ['id' => $log->user_id] : null),
                'actor_name' => $users[$log->user_id] ?? null,
                'actor_id' => $log->user_id,
                'subject_type' => $log->entity_type,
                'subject_id' => $log->entity_id,
                'branch_id' => $log->branch_id,
                'branch' => $log->branch_id !== null && isset($branches[$log->branch_id])
                    ? ['id' => $log->branch_id, 'name' => $branches[$log->branch_id]]
                    : null,
                'created_at' => $log->created_at?->toIso8601String(),
                'detail' => [
                    'before' => $log->before,
                    'after' => $log->after,
                ],
            ])->values()->all(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
