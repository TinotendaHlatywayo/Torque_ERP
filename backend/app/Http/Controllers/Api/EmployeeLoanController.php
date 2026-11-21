<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\EmployeeLoan;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeLoanController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $employeeId = (string) $request->query('employee_id', '');
        $status = (string) $request->query('status', '');

        $loans = $branch->employeeLoans()
            ->with(['employee', 'repayments'])
            ->when($employeeId !== '', fn ($query) => $query->where('employee_id', $employeeId))
            ->when(in_array($status, EmployeeLoan::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->orderByDesc('issued_date')
            ->get();

        return response()->json(['data' => $loans->map(fn (EmployeeLoan $employeeLoan): array => $this->payload($employeeLoan))]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'string', Rule::exists('employees', 'id')->where(fn ($query) => $query->where('branch_id', $branch->id))],
            'principal_minor' => ['required', 'integer', 'min:1'],
            'installment_minor' => ['required', 'integer', 'min:1', 'lte:principal_minor'],
            'issued_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $employeeLoan = $branch->employeeLoans()->create([
            ...$data,
            'balance_minor' => $data['principal_minor'],
            'status' => EmployeeLoan::STATUS_ACTIVE,
        ]);
        $employeeLoan->load('employee');
        $this->audit->log('employee_loan.created', $employeeLoan, null, $this->snapshot($employeeLoan), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($employeeLoan)], 201);
    }

    public function show(Branch $branch, EmployeeLoan $employeeLoan): JsonResponse
    {
        abort_unless($employeeLoan->branch_id === $branch->id, 404);

        return response()->json(['data' => $this->payload($employeeLoan->load(['employee', 'repayments']))]);
    }

    public function update(Request $request, Branch $branch, EmployeeLoan $employeeLoan): JsonResponse
    {
        abort_unless($employeeLoan->branch_id === $branch->id, 404);

        $data = $request->validate([
            'installment_minor' => ['sometimes', 'integer', 'min:1', 'max:'.$employeeLoan->balance_minor],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in([EmployeeLoan::STATUS_ACTIVE, EmployeeLoan::STATUS_CANCELLED])],
        ]);

        if ($employeeLoan->status === EmployeeLoan::STATUS_SETTLED) {
            throw ValidationException::withMessages(['loan' => 'Settled loans cannot be changed.']);
        }

        if (($data['status'] ?? null) === EmployeeLoan::STATUS_CANCELLED && $employeeLoan->balance_minor < $employeeLoan->principal_minor) {
            throw ValidationException::withMessages(['status' => 'Loans with repayments cannot be cancelled.']);
        }

        $before = $this->snapshot($employeeLoan);
        $employeeLoan->update($data);
        $this->audit->log('employee_loan.updated', $employeeLoan->refresh(), $before, $this->snapshot($employeeLoan), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($employeeLoan->refresh()->load(['employee', 'repayments']))]);
    }

    private function payload(EmployeeLoan $employeeLoan): array
    {
        return [
            'id' => $employeeLoan->id,
            'employee' => $employeeLoan->employee ? [
                'id' => $employeeLoan->employee->id,
                'employee_number' => $employeeLoan->employee->employee_number,
                'full_name' => trim($employeeLoan->employee->first_name.' '.$employeeLoan->employee->last_name),
            ] : null,
            'principal_minor' => $employeeLoan->principal_minor,
            'balance_minor' => $employeeLoan->balance_minor,
            'installment_minor' => $employeeLoan->installment_minor,
            'status' => $employeeLoan->status,
            'issued_date' => $employeeLoan->issued_date?->toDateString(),
            'notes' => $employeeLoan->notes,
            'repayments' => $employeeLoan->relationLoaded('repayments')
                ? $employeeLoan->repayments->map(fn ($repayment): array => [
                    'id' => $repayment->id,
                    'payslip_id' => $repayment->payslip_id,
                    'amount_minor' => $repayment->amount_minor,
                    'balance_before_minor' => $repayment->balance_before_minor,
                    'balance_after_minor' => $repayment->balance_after_minor,
                    'created_at' => $repayment->created_at?->toIso8601String(),
                ])
                : [],
        ];
    }

    private function snapshot(EmployeeLoan $employeeLoan): array
    {
        return [
            'employee_id' => $employeeLoan->employee_id,
            'principal_minor' => $employeeLoan->principal_minor,
            'balance_minor' => $employeeLoan->balance_minor,
            'installment_minor' => $employeeLoan->installment_minor,
            'status' => $employeeLoan->status,
            'issued_date' => $employeeLoan->issued_date?->toDateString(),
            'notes' => $employeeLoan->notes,
        ];
    }
}
