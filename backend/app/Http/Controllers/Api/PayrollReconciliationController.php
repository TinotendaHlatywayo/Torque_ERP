<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PayrollRun;
use App\Support\PayrollPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PayrollReconciliationController extends Controller
{
    public function __construct(private readonly PayrollPaymentService $payments) {}

    public function show(Branch $branch, PayrollRun $payrollRun): JsonResponse
    {
        abort_unless($payrollRun->branch_id === $branch->id, 404);

        return response()->json(['data' => $this->payments->reconcile($payrollRun)]);
    }

    public function export(Branch $branch, PayrollRun $payrollRun): Response
    {
        abort_unless($payrollRun->branch_id === $branch->id, 404);

        $lines = [['employee_number', 'employee_name', 'gross_minor', 'deductions_minor', 'net_minor', 'currency_code']];

        foreach ($payrollRun->payslips()->with('employee')->orderBy('id')->get() as $payslip) {
            $lines[] = [
                $payslip->employee?->employee_number ?? '',
                trim(($payslip->employee?->first_name ?? '').' '.($payslip->employee?->last_name ?? '')),
                (string) $payslip->gross_minor,
                (string) $payslip->deductions_minor,
                (string) $payslip->net_minor,
                $branch->currency_code,
            ];
        }

        $csv = '';
        foreach ($lines as $line) {
            $csv .= implode(',', array_map(static fn (string $value): string => '"'.str_replace('"', '""', $value).'"', $line))."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="payroll-run-'.$payrollRun->id.'.csv"',
        ]);
    }
}
