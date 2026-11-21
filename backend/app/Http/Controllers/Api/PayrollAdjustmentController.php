<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PayrollAdjustment;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PayrollAdjustmentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $employeeId = (string) $request->query('employee_id', '');
        $status = (string) $request->query('status', '');

        $adjustments = $branch->payrollAdjustments()
            ->with('employee')
            ->when($employeeId !== '', fn ($query) => $query->where('employee_id', $employeeId))
            ->when(in_array($status, PayrollAdjustment::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $adjustments->map(fn (PayrollAdjustment $payrollAdjustment): array => $this->payload($payrollAdjustment))]);
    }

    public function reverse(Request $request, Branch $branch, PayrollAdjustment $payrollAdjustment): JsonResponse
    {
        abort_unless($payrollAdjustment->branch_id === $branch->id, 404);

        if ($payrollAdjustment->status === PayrollAdjustment::STATUS_REVERSED) {
            throw ValidationException::withMessages(['adjustment' => 'This adjustment is already reversed.']);
        }

        $before = $this->snapshot($payrollAdjustment);

        if ($payrollAdjustment->status === PayrollAdjustment::STATUS_PENDING) {
            $payrollAdjustment->update([
                'status' => PayrollAdjustment::STATUS_REVERSED,
                'reversed_at' => now(),
            ]);
            $this->audit->log('payroll_adjustment.reversed', $payrollAdjustment->refresh(), $before, $this->snapshot($payrollAdjustment), $branch->id, $request->user());

            return response()->json(['data' => $this->payload($payrollAdjustment->refresh())]);
        }

        $reversal = $branch->payrollAdjustments()->create([
            'employee_id' => $payrollAdjustment->employee_id,
            'source_payslip_id' => $payrollAdjustment->source_payslip_id,
            'type' => $payrollAdjustment->type === PayrollAdjustment::TYPE_EARNING
                ? PayrollAdjustment::TYPE_DEDUCTION
                : PayrollAdjustment::TYPE_EARNING,
            'label' => 'Reversal: '.$payrollAdjustment->label,
            'amount_minor' => $payrollAdjustment->amount_minor,
            'reason' => 'Reversal of adjustment '.$payrollAdjustment->id.'. '.$payrollAdjustment->reason,
            'status' => PayrollAdjustment::STATUS_PENDING,
            'reverses_adjustment_id' => $payrollAdjustment->id,
        ]);
        $this->audit->log('payroll_adjustment.reversal_created', $reversal, null, $this->snapshot($reversal), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($reversal)], 201);
    }

    private function payload(PayrollAdjustment $payrollAdjustment): array
    {
        return [
            'id' => $payrollAdjustment->id,
            'employee' => $payrollAdjustment->employee ? [
                'id' => $payrollAdjustment->employee->id,
                'employee_number' => $payrollAdjustment->employee->employee_number,
                'full_name' => trim($payrollAdjustment->employee->first_name.' '.$payrollAdjustment->employee->last_name),
            ] : null,
            'source_payslip_id' => $payrollAdjustment->source_payslip_id,
            'type' => $payrollAdjustment->type,
            'label' => $payrollAdjustment->label,
            'amount_minor' => $payrollAdjustment->amount_minor,
            'reason' => $payrollAdjustment->reason,
            'status' => $payrollAdjustment->status,
            'applied_payslip_id' => $payrollAdjustment->applied_payslip_id,
            'applied_run_id' => $payrollAdjustment->applied_run_id,
            'reverses_adjustment_id' => $payrollAdjustment->reverses_adjustment_id,
            'applied_at' => $payrollAdjustment->applied_at?->toIso8601String(),
            'reversed_at' => $payrollAdjustment->reversed_at?->toIso8601String(),
        ];
    }

    private function snapshot(PayrollAdjustment $payrollAdjustment): array
    {
        return [
            'employee_id' => $payrollAdjustment->employee_id,
            'type' => $payrollAdjustment->type,
            'label' => $payrollAdjustment->label,
            'amount_minor' => $payrollAdjustment->amount_minor,
            'reason' => $payrollAdjustment->reason,
            'status' => $payrollAdjustment->status,
        ];
    }
}
