<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Platform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformBranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $branches = Branch::query()
            ->with(['platform:id,name', 'approver:id,name', 'suspender:id,name'])
            ->when(is_string($status) && $status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $branches->map(fn (Branch $branch) => $this->payload($branch))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:180'],
            'code' => ['required', 'string', 'max:40'],
            'slug' => ['required', 'string', 'max:80', 'unique:branches,slug'],
            'host' => ['nullable', 'string', 'max:180', 'unique:branches,host'],
            'timezone' => ['required', 'string', 'max:64'],
            'currency_code' => ['required', 'string', 'size:3'],
            'registration_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $platform = Platform::findOrFail($data['platform_id']);

        $branch = $platform->branches()->create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'slug' => $data['slug'],
            'host' => $data['host'] ?? null,
            'timezone' => $data['timezone'],
            'currency_code' => strtoupper($data['currency_code']),
            'status' => Branch::STATUS_PENDING,
            'settings' => isset($data['registration_notes']) ? ['registration_notes' => $data['registration_notes']] : null,
        ]);

        return response()->json(['data' => $this->payload($branch)], 201);
    }

    public function approve(Request $request, Branch $branch): JsonResponse
    {
        if ($branch->status !== Branch::STATUS_PENDING) {
            return response()->json(['message' => 'Only pending branches can be approved.'], 422);
        }

        $branch->update([
            'status' => Branch::STATUS_ACTIVE,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->payload($branch->refresh())]);
    }

    public function suspend(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($branch->status === Branch::STATUS_ARCHIVED) {
            return response()->json(['message' => 'Archived branches cannot be suspended.'], 422);
        }

        $branch->update([
            'status' => Branch::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'suspended_by' => $request->user()->id,
            'suspension_reason' => $data['reason'],
        ]);

        return response()->json(['data' => $this->payload($branch->refresh())]);
    }

    public function activate(Request $request, Branch $branch): JsonResponse
    {
        if (! in_array($branch->status, [Branch::STATUS_SUSPENDED, Branch::STATUS_PENDING], true)) {
            return response()->json(['message' => 'Only pending or suspended branches can be activated.'], 422);
        }

        $branch->update([
            'status' => Branch::STATUS_ACTIVE,
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
            'approved_at' => $branch->approved_at ?? now(),
            'approved_by' => $branch->approved_by ?? $request->user()->id,
        ]);

        return response()->json(['data' => $this->payload($branch->refresh())]);
    }

    public function archive(Request $request, Branch $branch): JsonResponse
    {
        $branch->update([
            'status' => Branch::STATUS_ARCHIVED,
            'archived_at' => now(),
        ]);

        $branch->delete();

        return response()->json(['data' => $this->payload($branch->refresh())]);
    }

    private function payload(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'platform_id' => $branch->platform_id,
            'platform' => $branch->platform?->name,
            'name' => $branch->name,
            'code' => $branch->code,
            'slug' => $branch->slug,
            'status' => $branch->status,
            'host' => $branch->host,
            'timezone' => $branch->timezone,
            'currency_code' => $branch->currency_code,
            'approved_at' => $branch->approved_at?->toIso8601String(),
            'approved_by' => $branch->approver?->name,
            'suspended_at' => $branch->suspended_at?->toIso8601String(),
            'suspension_reason' => $branch->suspension_reason,
            'archived_at' => $branch->archived_at?->toIso8601String(),
            'created_at' => $branch->created_at?->toIso8601String(),
        ];
    }
}
