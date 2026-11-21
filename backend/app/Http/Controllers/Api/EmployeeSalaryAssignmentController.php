<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\EmployeeSalaryAssignment;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeSalaryAssignmentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $employeeId = (string) $request->query('employee_id', '');

        $assignments = $branch->employeeSalaryAssignments()
            ->with(['employee', 'grade'])
            ->when($employeeId !== '', fn ($query) => $query->where('employee_id', $employeeId))
            ->orderByDesc('effective_from')
            ->get();

        return response()->json(['data' => $assignments->map(fn (EmployeeSalaryAssignment $employeeSalaryAssignment): array => $this->payload($employeeSalaryAssignment))]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'string', Rule::exists('employees', 'id')->where(fn ($query) => $query->where('branch_id', $branch->id))],
            'salary_grade_id' => ['required', 'string', Rule::exists('salary_grades', 'id')->where(fn ($query) => $query->where('branch_id', $branch->id))],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->assertNoOverlap($branch, $data);

        $employeeSalaryAssignment = $branch->employeeSalaryAssignments()->create($data);
        $employeeSalaryAssignment->load(['employee', 'grade']);
        $this->audit->log('employee_salary_assignment.created', $employeeSalaryAssignment, null, $this->snapshot($employeeSalaryAssignment), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($employeeSalaryAssignment)], 201);
    }

    public function update(Request $request, Branch $branch, EmployeeSalaryAssignment $employeeSalaryAssignment): JsonResponse
    {
        abort_unless($employeeSalaryAssignment->branch_id === $branch->id, 404);

        $data = $request->validate([
            'salary_grade_id' => ['sometimes', 'string', Rule::exists('salary_grades', 'id')->where(fn ($query) => $query->where('branch_id', $branch->id))],
            'effective_from' => ['sometimes', 'date'],
            'effective_to' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $merged = array_merge([
            'employee_id' => $employeeSalaryAssignment->employee_id,
            'salary_grade_id' => $employeeSalaryAssignment->salary_grade_id,
            'effective_from' => $employeeSalaryAssignment->effective_from->toDateString(),
            'effective_to' => $employeeSalaryAssignment->effective_to?->toDateString(),
        ], $data);

        if (! empty($merged['effective_to']) && $merged['effective_to'] < $merged['effective_from']) {
            throw ValidationException::withMessages(['effective_to' => 'The end date must be on or after the start date.']);
        }

        $this->assertNoOverlap($branch, $merged, $employeeSalaryAssignment->id);

        $before = $this->snapshot($employeeSalaryAssignment);
        $employeeSalaryAssignment->update($data);
        $employeeSalaryAssignment->load(['employee', 'grade']);
        $this->audit->log('employee_salary_assignment.updated', $employeeSalaryAssignment->refresh(), $before, $this->snapshot($employeeSalaryAssignment), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($employeeSalaryAssignment->refresh())]);
    }

    private function assertNoOverlap(Branch $branch, array $data, ?string $ignoreId = null): void
    {
        $from = $data['effective_from'];
        $to = $data['effective_to'] ?? null;

        $overlap = $branch->employeeSalaryAssignments()
            ->where('employee_id', $data['employee_id'])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereDate('effective_from', '<=', $to ?? '9999-12-31')
            ->where(function ($query) use ($from): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from);
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['effective_from' => 'This salary assignment overlaps an existing assignment for the employee.']);
        }
    }

    private function payload(EmployeeSalaryAssignment $employeeSalaryAssignment): array
    {
        return [
            'id' => $employeeSalaryAssignment->id,
            'employee' => $employeeSalaryAssignment->employee ? [
                'id' => $employeeSalaryAssignment->employee->id,
                'employee_number' => $employeeSalaryAssignment->employee->employee_number,
                'full_name' => trim($employeeSalaryAssignment->employee->first_name.' '.$employeeSalaryAssignment->employee->last_name),
            ] : null,
            'salary_grade' => $employeeSalaryAssignment->grade ? [
                'id' => $employeeSalaryAssignment->grade->id,
                'name' => $employeeSalaryAssignment->grade->name,
                'code' => $employeeSalaryAssignment->grade->code,
                'pay_basis' => $employeeSalaryAssignment->grade->pay_basis,
                'base_rate_minor' => $employeeSalaryAssignment->grade->base_rate_minor,
                'currency_code' => $employeeSalaryAssignment->grade->currency_code,
            ] : null,
            'effective_from' => $employeeSalaryAssignment->effective_from?->toDateString(),
            'effective_to' => $employeeSalaryAssignment->effective_to?->toDateString(),
            'notes' => $employeeSalaryAssignment->notes,
        ];
    }

    private function snapshot(EmployeeSalaryAssignment $employeeSalaryAssignment): array
    {
        return [
            'employee_id' => $employeeSalaryAssignment->employee_id,
            'salary_grade_id' => $employeeSalaryAssignment->salary_grade_id,
            'effective_from' => $employeeSalaryAssignment->effective_from?->toDateString(),
            'effective_to' => $employeeSalaryAssignment->effective_to?->toDateString(),
            'notes' => $employeeSalaryAssignment->notes,
        ];
    }
}
