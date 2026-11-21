<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\LeaveType;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveTypeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $status = (string) $request->query('status', 'active');

        $types = $branch->leaveTypes()
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $types->map(fn (LeaveType $type): array => $this->payload($type)),
        ]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $this->validated($request, $branch);

        $type = $branch->leaveTypes()->create([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'is_paid' => $data['is_paid'] ?? true,
            'requires_approval' => $data['requires_approval'] ?? true,
            'max_days_per_year' => $data['max_days_per_year'] ?? null,
            'counts_weekends' => $data['counts_weekends'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->audit->log('leave_type.created', $type, null, $this->snapshot($type), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($type)], 201);
    }

    public function update(Request $request, Branch $branch, LeaveType $leaveType): JsonResponse
    {
        $before = $this->snapshot($leaveType);
        $data = $this->validated($request, $branch, $leaveType);

        if (array_key_exists('code', $data)) {
            $data['code'] = strtoupper($data['code']);
        }

        $leaveType->update($data);

        $this->audit->log('leave_type.updated', $leaveType->refresh(), $before, $this->snapshot($leaveType), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($leaveType->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Branch $branch, ?LeaveType $leaveType = null): array
    {
        $required = $leaveType ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'code' => [
                $required,
                'string',
                'max:30',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('leave_types', 'code')->where('branch_id', $branch->id)->ignore($leaveType?->id),
            ],
            'is_paid' => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
            'max_days_per_year' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:366'],
            'counts_weekends' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LeaveType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'code' => $type->code,
            'is_paid' => $type->is_paid,
            'requires_approval' => $type->requires_approval,
            'max_days_per_year' => $type->max_days_per_year,
            'counts_weekends' => $type->counts_weekends,
            'is_active' => $type->is_active,
            'created_at' => $type->created_at?->toIso8601String(),
            'updated_at' => $type->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(LeaveType $type): array
    {
        return [
            'name' => $type->name,
            'code' => $type->code,
            'is_paid' => $type->is_paid,
            'requires_approval' => $type->requires_approval,
            'max_days_per_year' => $type->max_days_per_year,
            'counts_weekends' => $type->counts_weekends,
            'is_active' => $type->is_active,
        ];
    }
}
