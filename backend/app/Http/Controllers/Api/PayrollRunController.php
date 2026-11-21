<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Support\AuditLogger;
use App\Support\PayrollEngine;
use App\Support\PayrollPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollEngine $engine,
        private readonly AuditLogger $audit,
        private readonly PayrollPaymentService $payments,
    ) {}

    public function index(Branch $branch, PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless($payrollPeriod->branch_id === $branch->id, 404);

        $runs = $payrollPeriod->runs()->orderByDesc('version')->get();

        return response()->json(['data' => $runs->map(fn (PayrollRun $run): array => $this->summary($run))]);
    }

    public function generate(Request $request, Branch $branch, PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless($payrollPeriod->branch_id === $branch->id, 404);

        $run = $this->engine->generate($payrollPeriod, $request->user()->id);
        $this->audit->log('payroll_run.generated', $run, null, $this->snapshot($run), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($run)], 201);
    }

    public function show(Branch $branch, PayrollRun $payrollRun): JsonResponse
    {
        abort_unless($payrollRun->branch_id === $branch->id, 404);

        return response()->json(['data' => $this->payload($payrollRun->load(['period', 'payslips.employee', 'payslips.lines']))]);
    }

    public function approve(Request $request, Branch $branch, PayrollRun $payrollRun): JsonResponse
    {
        abort_unless($payrollRun->branch_id === $branch->id, 404);

        $before = $this->snapshot($payrollRun);
        $run = DB::transaction(function () use ($payrollRun, $request): PayrollRun {
            $approved = $this->engine->approve($payrollRun, $request->user()->id);
            $this->payments->accrue($approved, $request->user());

            return $approved;
        }, 3);
        $this->audit->log('payroll_run.approved', $run, $before, $this->snapshot($run), $branch->id, $request->user());
        $this->audit->log('payroll_period.locked', $run->period, null, $this->periodSnapshot($run->period), $branch->id, $request->user());

        return response()->json(['data' => $this->payload($run)]);
    }

    private function payload(PayrollRun $run): array
    {
        return [
            ...$this->summary($run),
            'period' => $run->period ? [
                'id' => $run->period->id,
                'start_date' => $run->period->start_date?->toDateString(),
                'end_date' => $run->period->end_date?->toDateString(),
                'pay_date' => $run->period->pay_date?->toDateString(),
                'status' => $run->period->status,
            ] : null,
            'payslips' => $run->relationLoaded('payslips')
                ? $run->payslips->map(fn (Payslip $payslip): array => $this->payslipPayload($payslip))
                : [],
        ];
    }

    private function summary(PayrollRun $run): array
    {
        return [
            'id' => $run->id,
            'payroll_period_id' => $run->payroll_period_id,
            'kind' => $run->kind,
            'version' => $run->version,
            'status' => $run->status,
            'gross_minor' => $run->gross_minor,
            'deductions_minor' => $run->deductions_minor,
            'employer_statutory_minor' => $run->employer_statutory_minor,
            'net_minor' => $run->net_minor,
            'warnings' => $run->warnings ?? [],
            'approved_at' => $run->approved_at?->toIso8601String(),
        ];
    }

    private function payslipPayload(Payslip $payslip): array
    {
        return [
            'id' => $payslip->id,
            'employee' => $payslip->employee ? [
                'id' => $payslip->employee->id,
                'employee_number' => $payslip->employee->employee_number,
                'full_name' => trim($payslip->employee->first_name.' '.$payslip->employee->last_name),
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

    private function snapshot(PayrollRun $run): array
    {
        return [
            'status' => $run->status,
            'version' => $run->version,
            'gross_minor' => $run->gross_minor,
            'deductions_minor' => $run->deductions_minor,
            'net_minor' => $run->net_minor,
        ];
    }

    private function periodSnapshot(PayrollPeriod $period): array
    {
        return [
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
            'status' => $period->status,
            'locked_at' => $period->locked_at?->toIso8601String(),
        ];
    }
}
