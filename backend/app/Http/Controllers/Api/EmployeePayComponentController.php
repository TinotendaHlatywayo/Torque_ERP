<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\EmployeePayComponent;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeePayComponentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $employeeId = (string) $request->query('employee_id', '');

        $components = $branch->employeePayComponents()
            ->with('employee')
            ->when($employeeId !== '', fn ($query) => $query->where('employee_id', $employeeId))
            ->orderBy('employee_id')
            ->orderBy('code')
            ->get();

        return response()->json(['data' => $components->map(fn (EmployeePayComponent $employeePayComponent): array => $this->payload($employeePayComponent))]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $this->validated($request, $branch);
        $data['code'] = strtoupper($data['code']);
        $this->assertNoOverlap($branch, $data);

        $employeePayComponent = $branch->employeePayComponents()->create($data);
        $employeePayComponent->load('employee');
        $this->audit->log('employee_pay_component.created', $employeePayComponent, null, $this->snapshot($employeePayComponent), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($employeePayComponent)], 201);
    }

    public function update(Request $request, Branch $branch, EmployeePayComponent $employeePayComponent): JsonResponse
    {
        abort_unless($employeePayComponent->branch_id === $branch->id, 404);

        $data = $this->validated($request, $branch, $employeePayComponent);
        $data['code'] = strtoupper($data['code'] ?? $employeePayComponent->code);
        $merged = array_merge([
            'employee_id' => $employeePayComponent->employee_id,
            'type' => $employeePayComponent->type,
            'code' => $employeePayComponent->code,
            'label' => $employeePayComponent->label,
            'amount_minor' => $employeePayComponent->amount_minor,
            'effective_from' => $employeePayComponent->effective_from->toDateString(),
            'effective_to' => $employeePayComponent->effective_to?->toDateString(),
        ], $data);

        if (! empty($merged['effective_to']) && $merged['effective_to'] < $merged['effective_from']) {
            throw ValidationException::withMessages(['effective_to' => 'The end date must be on or after the start date.']);
        }

        $this->assertNoOverlap($branch, $merged, $employeePayComponent->id);

        $before = $this->snapshot($employeePayComponent);
        $employeePayComponent->update($data);
        $employeePayComponent->load('employee');
        $this->audit->log('employee_pay_component.updated', $employeePayComponent->refresh(), $before, $this->snapshot($employeePayComponent), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($employeePayComponent->refresh())]);
    }

    public function destroy(Request $request, Branch $branch, EmployeePayComponent $employeePayComponent): JsonResponse
    {
        abort_unless($employeePayComponent->branch_id === $branch->id, 404);

        if (! $employeePayComponent->is_active) {
            return response()->json(['data' => $this->payload($employeePayComponent)]);
        }

        $before = $this->snapshot($employeePayComponent);
        $employeePayComponent->update(['is_active' => false]);
        $this->audit->log('employee_pay_component.deactivated', $employeePayComponent->refresh(), $before, $this->snapshot($employeePayComponent), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($employeePayComponent->refresh())]);
    }

    private function validated(Request $request, Branch $branch, ?EmployeePayComponent $employeePayComponent = null): array
    {
        return $request->validate([
            'employee_id' => $employeePayComponent ? ['sometimes'] : ['required', 'string', Rule::exists('employees', 'id')->where(fn ($query) => $query->where('branch_id', $branch->id))],
            'type' => $employeePayComponent ? ['sometimes', Rule::in(EmployeePayComponent::TYPES)] : ['required', Rule::in(EmployeePayComponent::TYPES)],
            'code' => $employeePayComponent ? ['sometimes', 'string', 'max:30'] : ['required', 'string', 'max:30'],
            'label' => $employeePayComponent ? ['sometimes', 'string', 'max:120'] : ['required', 'string', 'max:120'],
            'amount_minor' => $employeePayComponent ? ['sometimes', 'integer', 'min:1'] : ['required', 'integer', 'min:1'],
            'effective_from' => $employeePayComponent ? ['sometimes', 'date'] : ['required', 'date'],
            'effective_to' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function assertNoOverlap(Branch $branch, array $data, ?string $ignoreId = null): void
    {
        $overlap = $branch->employeePayComponents()
            ->where('employee_id', $data['employee_id'])
            ->where('code', $data['code'] ?? '')
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereDate('effective_from', '<=', $data['effective_to'] ?? '9999-12-31')
            ->where(function ($query) use ($data): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $data['effective_from']);
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['effective_from' => 'This pay component overlaps an existing component with the same code.']);
        }
    }

    private function payload(EmployeePayComponent $employeePayComponent): array
    {
        return [
            'id' => $employeePayComponent->id,
            'employee' => $employeePayComponent->employee ? [
                'id' => $employeePayComponent->employee->id,
                'employee_number' => $employeePayComponent->employee->employee_number,
                'full_name' => trim($employeePayComponent->employee->first_name.' '.$employeePayComponent->employee->last_name),
            ] : null,
            'type' => $employeePayComponent->type,
            'code' => $employeePayComponent->code,
            'label' => $employeePayComponent->label,
            'amount_minor' => $employeePayComponent->amount_minor,
            'effective_from' => $employeePayComponent->effective_from?->toDateString(),
            'effective_to' => $employeePayComponent->effective_to?->toDateString(),
            'is_active' => $employeePayComponent->is_active,
        ];
    }

    private function snapshot(EmployeePayComponent $employeePayComponent): array
    {
        return [
            'employee_id' => $employeePayComponent->employee_id,
            'type' => $employeePayComponent->type,
            'code' => $employeePayComponent->code,
            'label' => $employeePayComponent->label,
            'amount_minor' => $employeePayComponent->amount_minor,
            'effective_from' => $employeePayComponent->effective_from?->toDateString(),
            'effective_to' => $employeePayComponent->effective_to?->toDateString(),
            'is_active' => $employeePayComponent->is_active,
        ];
    }
}
