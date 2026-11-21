<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PayslipController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Branch $branch, Payslip $payslip): JsonResponse
    {
        abort_unless($payslip->branch_id === $branch->id, 404);

        return response()->json(['data' => $this->payload($payslip->load(['employee', 'run.period', 'lines']))]);
    }

    public function adjustment(Request $request, Branch $branch, Payslip $payslip): JsonResponse
    {
        abort_unless($payslip->branch_id === $branch->id, 404);

        $run = $payslip->run;

        if ($run->status !== PayrollRun::STATUS_APPROVED) {
            throw ValidationException::withMessages(['payslip' => 'Adjustments can only be raised against an approved payslip.']);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(PayrollAdjustment::TYPES)],
            'label' => ['required', 'string', 'max:120'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $adjustment = $branch->payrollAdjustments()->create([
            ...$data,
            'employee_id' => $payslip->employee_id,
            'source_payslip_id' => $payslip->id,
            'status' => PayrollAdjustment::STATUS_PENDING,
        ]);
        $this->audit->log('payroll_adjustment.created', $adjustment, null, $this->adjustmentSnapshot($adjustment), $branch->id, $request->user());

        return response()->json(['data' => $this->adjustmentPayload($adjustment)], 201);
    }

    private function payload(Payslip $payslip): array
    {
        return [
            'id' => $payslip->id,
            'employee' => $payslip->employee ? [
                'id' => $payslip->employee->id,
                'employee_number' => $payslip->employee->employee_number,
                'full_name' => trim($payslip->employee->first_name.' '.$payslip->employee->last_name),
            ] : null,
            'run' => $payslip->run ? [
                'id' => $payslip->run->id,
                'status' => $payslip->run->status,
                'version' => $payslip->run->version,
                'period' => $payslip->run->period ? [
                    'id' => $payslip->run->period->id,
                    'start_date' => $payslip->run->period->start_date?->toDateString(),
                    'end_date' => $payslip->run->period->end_date?->toDateString(),
                    'pay_date' => $payslip->run->period->pay_date?->toDateString(),
                ] : null,
            ] : null,
            'pay_basis' => $payslip->pay_basis,
            'base_rate_minor' => $payslip->base_rate_minor,
            'base_pay_minor' => $payslip->base_pay_minor,
            'overtime_minor' => $payslip->overtime_minor,
            'allowances_minor' => $payslip->allowances_minor,
            'adjustment_earnings_minor' => $payslip->adjustment_earnings_minor,
            'pre_tax_deductions_minor' => $payslip->pre_tax_deductions_minor,
            'statutory_employee_minor' => $payslip->statutory_employee_minor,
            'loan_deductions_minor' => $payslip->loan_deductions_minor,
            'adjustment_deductions_minor' => $payslip->adjustment_deductions_minor,
            'gross_minor' => $payslip->gross_minor,
            'deductions_minor' => $payslip->deductions_minor,
            'employer_statutory_minor' => $payslip->employer_statutory_minor,
            'net_minor' => $payslip->net_minor,
            'period_working_days' => $payslip->period_working_days,
            'payable_working_days' => $payslip->payable_working_days,
            'regular_minutes' => $payslip->regular_minutes,
            'overtime_minutes' => $payslip->overtime_minutes,
            'breakdown' => $payslip->breakdown,
            'lines' => $payslip->relationLoaded('lines')
                ? $payslip->lines->map(fn ($line): array => [
                    'id' => $line->id,
                    'category' => $line->category,
                    'code' => $line->code,
                    'label' => $line->label,
                    'amount_minor' => $line->amount_minor,
                    'units' => $line->units,
                    'rate_minor' => $line->rate_minor,
                    'source_type' => $line->source_type,
                    'source_id' => $line->source_id,
                    'metadata' => $line->metadata,
                ])
                : [],
        ];
    }

    private function adjustmentPayload(PayrollAdjustment $adjustment): array
    {
        return [
            'id' => $adjustment->id,
            'employee_id' => $adjustment->employee_id,
            'source_payslip_id' => $adjustment->source_payslip_id,
            'type' => $adjustment->type,
            'label' => $adjustment->label,
            'amount_minor' => $adjustment->amount_minor,
            'reason' => $adjustment->reason,
            'status' => $adjustment->status,
            'applied_payslip_id' => $adjustment->applied_payslip_id,
            'applied_run_id' => $adjustment->applied_run_id,
            'reverses_adjustment_id' => $adjustment->reverses_adjustment_id,
        ];
    }

    private function adjustmentSnapshot(PayrollAdjustment $adjustment): array
    {
        return [
            'employee_id' => $adjustment->employee_id,
            'source_payslip_id' => $adjustment->source_payslip_id,
            'type' => $adjustment->type,
            'label' => $adjustment->label,
            'amount_minor' => $adjustment->amount_minor,
            'reason' => $adjustment->reason,
            'status' => $adjustment->status,
        ];
    }
}
