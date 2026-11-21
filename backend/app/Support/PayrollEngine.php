<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\EmployeePayComponent;
use App\Models\EmployeeSalaryAssignment;
use App\Models\LoanRepayment;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollStatutoryRule;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\SalaryGrade;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PayrollEngine
{
    public function generate(PayrollPeriod $period, int $userId): PayrollRun
    {
        if ($period->isLocked()) {
            throw ValidationException::withMessages(['period' => 'This payroll period is locked. Use an adjustment in a future period.']);
        }

        $start = CarbonImmutable::parse($period->start_date);
        $end = CarbonImmutable::parse($period->end_date);
        $periodWorkingDays = $this->workingDays($start, $end);

        if ($periodWorkingDays === 0) {
            throw ValidationException::withMessages(['period' => 'The payroll period contains no working days.']);
        }

        return DB::transaction(function () use ($period, $start, $end, $periodWorkingDays, $userId): PayrollRun {
            $existing = $period->runs()->where('kind', PayrollRun::KIND_REGULAR)->first();

            if ($existing?->status === PayrollRun::STATUS_APPROVED) {
                throw ValidationException::withMessages(['period' => 'An approved payroll run already exists for this period.']);
            }

            $version = (int) $period->runs()->max('version') + 1;
            $existing?->delete();

            $run = $period->runs()->create([
                'branch_id' => $period->branch_id,
                'kind' => PayrollRun::KIND_REGULAR,
                'version' => $version,
                'status' => PayrollRun::STATUS_DRAFT,
                'generated_by' => $userId,
            ]);

            $warnings = [];
            $gross = 0;
            $deductions = 0;
            $employerStatutory = 0;
            $net = 0;

            $employees = Employee::query()
                ->where('branch_id', $period->branch_id)
                ->whereIn('status', [Employee::STATUS_HIRED, Employee::STATUS_ACTIVE, Employee::STATUS_TERMINATED])
                ->whereDate('hire_date', '<=', $end->toDateString())
                ->where(function ($query) use ($start): void {
                    $query->whereNull('termination_date')->orWhereDate('termination_date', '>=', $start->toDateString());
                })
                ->orderBy('employee_number')
                ->get();

            foreach ($employees as $employee) {
                $assignment = $this->assignmentFor($employee, $start, $end);

                if ($assignment === null) {
                    $warnings[] = "Employee {$employee->employee_number} has no salary assignment effective in this period.";

                    continue;
                }

                $payslip = $this->buildPayslip($run, $period, $employee, $assignment, $start, $end, $periodWorkingDays);

                if ($payslip === null) {
                    $warnings[] = "Employee {$employee->employee_number} has no payable working days in this period.";

                    continue;
                }

                $gross += $payslip->gross_minor;
                $deductions += $payslip->deductions_minor;
                $employerStatutory += $payslip->employer_statutory_minor;
                $net += $payslip->net_minor;
            }

            $run->update([
                'gross_minor' => $gross,
                'deductions_minor' => $deductions,
                'employer_statutory_minor' => $employerStatutory,
                'net_minor' => $net,
                'warnings' => $warnings ?: null,
            ]);

            return $run->refresh()->load(['payslips.lines', 'payslips.employee']);
        });
    }

    public function approve(PayrollRun $run, int $userId): PayrollRun
    {
        if ($run->status !== PayrollRun::STATUS_DRAFT) {
            throw ValidationException::withMessages(['run' => 'Only draft payroll runs can be approved.']);
        }

        $period = $run->period;

        if ($period->isLocked()) {
            throw ValidationException::withMessages(['period' => 'This payroll period is already locked.']);
        }

        return DB::transaction(function () use ($run, $period, $userId): PayrollRun {
            $run = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($run->status !== PayrollRun::STATUS_DRAFT) {
                throw ValidationException::withMessages(['run' => 'Only draft payroll runs can be approved.']);
            }

            $period = PayrollPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();

            if ($period->isLocked()) {
                throw ValidationException::withMessages(['period' => 'This payroll period is already locked.']);
            }

            $run->load(['payslips.lines', 'payslips.employee']);

            foreach ($run->payslips as $payslip) {
                foreach ($payslip->lines->where('category', PayslipLine::CATEGORY_LOAN) as $line) {
                    $loan = EmployeeLoan::where('branch_id', $run->branch_id)
                        ->whereKey($line->source_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($loan->status !== EmployeeLoan::STATUS_ACTIVE) {
                        throw ValidationException::withMessages(['run' => 'A loan in this run is no longer active. Regenerate the draft.']);
                    }

                    if ($loan->balance_minor < $line->amount_minor) {
                        throw ValidationException::withMessages(['run' => 'A loan balance changed after this run was generated. Regenerate the draft.']);
                    }

                    $before = $loan->balance_minor;
                    $after = $before - $line->amount_minor;
                    $loan->update([
                        'balance_minor' => $after,
                        'status' => $after === 0 ? EmployeeLoan::STATUS_SETTLED : EmployeeLoan::STATUS_ACTIVE,
                    ]);

                    LoanRepayment::create([
                        'branch_id' => $run->branch_id,
                        'employee_loan_id' => $loan->id,
                        'payslip_id' => $payslip->id,
                        'payroll_run_id' => $run->id,
                        'amount_minor' => $line->amount_minor,
                        'balance_before_minor' => $before,
                        'balance_after_minor' => $after,
                    ]);
                }

                $adjustmentIds = $payslip->lines
                    ->where('source_type', PayrollAdjustment::class)
                    ->pluck('source_id');

                PayrollAdjustment::where('branch_id', $run->branch_id)
                    ->whereIn('id', $adjustmentIds)
                    ->where('status', PayrollAdjustment::STATUS_PENDING)
                    ->update([
                        'status' => PayrollAdjustment::STATUS_APPLIED,
                        'applied_payslip_id' => $payslip->id,
                        'applied_run_id' => $run->id,
                        'applied_at' => now(),
                    ]);
            }

            $run->update([
                'status' => PayrollRun::STATUS_APPROVED,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            $period->update([
                'status' => PayrollPeriod::STATUS_LOCKED,
                'locked_at' => now(),
                'locked_by' => $userId,
            ]);

            return $run->refresh()->load(['payslips.lines', 'payslips.employee']);
        });
    }

    /**
     * @return array{0: PayrollPeriod, 1: PayrollRun}
     */
    public function periodAndRun(PayrollPeriod $period): array
    {
        return [$period, $period->runs()->where('kind', PayrollRun::KIND_REGULAR)->firstOrFail()];
    }

    private function assignmentFor(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): ?EmployeeSalaryAssignment
    {
        return $employee->salaryAssignments()
            ->whereDate('effective_from', '<=', $end->toDateString())
            ->where(function ($query) use ($start): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    private function buildPayslip(
        PayrollRun $run,
        PayrollPeriod $period,
        Employee $employee,
        EmployeeSalaryAssignment $assignment,
        CarbonImmutable $start,
        CarbonImmutable $end,
        int $periodWorkingDays,
    ): ?Payslip {
        $grade = $assignment->grade;

        if (! $grade instanceof SalaryGrade) {
            return null;
        }

        $employmentStart = $start->max(CarbonImmutable::parse($employee->hire_date));
        $employmentEnd = $employee->termination_date
            ? $end->min(CarbonImmutable::parse($employee->termination_date))
            : $end;
        $payableDays = $this->workingDays($employmentStart, $employmentEnd);

        if ($payableDays === 0) {
            return null;
        }

        $attendance = AttendanceRecord::query()
            ->where('branch_id', $period->branch_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $regularMinutes = (int) $attendance->sum('worked_minutes');
        $overtimeMinutes = (int) $attendance->sum('overtime_minutes');

        $basePay = $grade->pay_basis === SalaryGrade::BASIS_HOURLY
            ? $this->perMinute($grade->base_rate_minor, $regularMinutes)
            : $this->proRate($grade->base_rate_minor, $payableDays, $periodWorkingDays);

        $overtimeRate = $grade->overtime_hourly_rate_minor ?? ($grade->pay_basis === SalaryGrade::BASIS_HOURLY ? $grade->base_rate_minor : 0);
        $overtimePay = $overtimeRate > 0
            ? $this->perMinute($this->percent($overtimeRate, (float) $grade->overtime_multiplier * 100), $overtimeMinutes)
            : 0;

        $components = EmployeePayComponent::query()
            ->where('branch_id', $period->branch_id)
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $end->toDateString())
            ->where(function ($query) use ($start): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start->toDateString());
            })
            ->orderBy('code')
            ->get();

        $allowances = (int) $components->where('type', EmployeePayComponent::TYPE_ALLOWANCE)->sum('amount_minor');
        $preTaxDeductions = (int) $components->where('type', EmployeePayComponent::TYPE_DEDUCTION)->sum('amount_minor');

        $adjustments = PayrollAdjustment::query()
            ->where('branch_id', $period->branch_id)
            ->where('employee_id', $employee->id)
            ->where('status', PayrollAdjustment::STATUS_PENDING)
            ->orderBy('created_at')
            ->get();

        $adjustmentEarnings = (int) $adjustments->where('type', PayrollAdjustment::TYPE_EARNING)->sum('amount_minor');
        $adjustmentDeductions = (int) $adjustments->where('type', PayrollAdjustment::TYPE_DEDUCTION)->sum('amount_minor');

        $gross = $basePay + $overtimePay + $allowances + $adjustmentEarnings;

        $statutoryRules = PayrollStatutoryRule::query()
            ->where('branch_id', $period->branch_id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $end->toDateString())
            ->where(function ($query) use ($start): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start->toDateString());
            })
            ->orderBy('code')
            ->get();

        $statutoryEmployee = 0;
        $statutoryEmployer = 0;
        $statutoryLines = [];

        foreach ($statutoryRules as $rule) {
            $basis = $rule->applies_to === PayrollStatutoryRule::APPLIES_BASIC
                ? $basePay + $overtimePay
                : $gross;

            $employeeAmount = $rule->fixed_employee_minor + $this->percent($basis, (float) $rule->employee_rate);
            $employerAmount = $rule->fixed_employer_minor + $this->percent($basis, (float) $rule->employer_rate);

            if ($employeeAmount > 0) {
                $statutoryEmployee += $employeeAmount;
                $statutoryLines[] = [
                    'category' => PayslipLine::CATEGORY_STATUTORY_EMPLOYEE,
                    'code' => $rule->code,
                    'label' => $rule->name.' (employee)',
                    'amount_minor' => $employeeAmount,
                    'metadata' => ['basis' => $rule->applies_to, 'rate' => (string) $rule->employee_rate],
                ];
            }

            if ($employerAmount > 0) {
                $statutoryEmployer += $employerAmount;
                $statutoryLines[] = [
                    'category' => PayslipLine::CATEGORY_STATUTORY_EMPLOYER,
                    'code' => $rule->code,
                    'label' => $rule->name.' (employer)',
                    'amount_minor' => $employerAmount,
                    'metadata' => ['basis' => $rule->applies_to, 'rate' => (string) $rule->employer_rate],
                ];
            }
        }

        $loans = EmployeeLoan::query()
            ->where('branch_id', $period->branch_id)
            ->where('employee_id', $employee->id)
            ->where('status', EmployeeLoan::STATUS_ACTIVE)
            ->whereDate('issued_date', '<=', $end->toDateString())
            ->orderBy('issued_date')
            ->get();

        $loanDeductions = 0;
        $loanLines = [];

        foreach ($loans as $loan) {
            $amount = min($loan->installment_minor, $loan->balance_minor);

            if ($amount > 0) {
                $loanDeductions += $amount;
                $loanLines[] = [
                    'category' => PayslipLine::CATEGORY_LOAN,
                    'code' => 'LOAN',
                    'label' => 'Loan repayment',
                    'amount_minor' => $amount,
                    'source_id' => $loan->id,
                    'metadata' => ['balance_before_minor' => $loan->balance_minor],
                ];
            }
        }

        $deductions = $preTaxDeductions + $statutoryEmployee + $loanDeductions + $adjustmentDeductions;

        $payslip = Payslip::create([
            'branch_id' => $period->branch_id,
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'salary_grade_id' => $grade->id,
            'pay_basis' => $grade->pay_basis,
            'base_rate_minor' => $grade->base_rate_minor,
            'base_pay_minor' => $basePay,
            'overtime_minor' => $overtimePay,
            'allowances_minor' => $allowances,
            'adjustment_earnings_minor' => $adjustmentEarnings,
            'pre_tax_deductions_minor' => $preTaxDeductions,
            'statutory_employee_minor' => $statutoryEmployee,
            'loan_deductions_minor' => $loanDeductions,
            'adjustment_deductions_minor' => $adjustmentDeductions,
            'gross_minor' => $gross,
            'deductions_minor' => $deductions,
            'employer_statutory_minor' => $statutoryEmployer,
            'net_minor' => $gross - $deductions,
            'period_working_days' => $periodWorkingDays,
            'payable_working_days' => $payableDays,
            'regular_minutes' => $regularMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'breakdown' => [
                'currency_code' => $grade->currency_code,
                'pay_basis' => $grade->pay_basis,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'standard_daily_minutes' => 480,
                'overtime_multiplier' => (string) $grade->overtime_multiplier,
            ],
        ]);

        $lines = [];

        $lines[] = [
            'category' => PayslipLine::CATEGORY_EARNING,
            'code' => 'BASE',
            'label' => 'Base pay',
            'amount_minor' => $basePay,
            'units' => $grade->pay_basis === SalaryGrade::BASIS_HOURLY ? $regularMinutes / 60 : $payableDays,
            'rate_minor' => $grade->base_rate_minor,
        ];

        if ($overtimePay > 0) {
            $lines[] = [
                'category' => PayslipLine::CATEGORY_EARNING,
                'code' => 'OT',
                'label' => 'Overtime',
                'amount_minor' => $overtimePay,
                'units' => $overtimeMinutes / 60,
                'rate_minor' => $overtimeRate,
                'metadata' => ['multiplier' => (string) $grade->overtime_multiplier],
            ];
        }

        foreach ($components as $component) {
            $lines[] = [
                'category' => $component->type === EmployeePayComponent::TYPE_ALLOWANCE
                    ? PayslipLine::CATEGORY_EARNING
                    : PayslipLine::CATEGORY_DEDUCTION,
                'code' => $component->code,
                'label' => $component->label,
                'amount_minor' => $component->amount_minor,
                'source_type' => EmployeePayComponent::class,
                'source_id' => $component->id,
            ];
        }

        foreach ($adjustments as $adjustment) {
            $lines[] = [
                'category' => $adjustment->type === PayrollAdjustment::TYPE_EARNING
                    ? PayslipLine::CATEGORY_EARNING
                    : PayslipLine::CATEGORY_DEDUCTION,
                'code' => 'ADJ',
                'label' => $adjustment->label,
                'amount_minor' => $adjustment->amount_minor,
                'source_type' => PayrollAdjustment::class,
                'source_id' => $adjustment->id,
                'metadata' => ['reason' => $adjustment->reason],
            ];
        }

        $lines = array_merge($lines, $statutoryLines, $loanLines);

        foreach ($lines as $line) {
            if (($line['amount_minor'] ?? 0) <= 0) {
                continue;
            }

            $payslip->lines()->create($line);
        }

        return $payslip;
    }

    private function workingDays(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        $days = 0;
        $cursor = $start;

        while ($cursor->lte($end)) {
            if (! $cursor->isWeekend()) {
                $days++;
            }

            $cursor = $cursor->addDay();
        }

        return $days;
    }

    private function proRate(int $amount, int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            return 0;
        }

        return (int) round($amount * $numerator / $denominator, 0, PHP_ROUND_HALF_UP);
    }

    private function perMinute(int $hourlyRateMinor, int $minutes): int
    {
        return $this->proRate($hourlyRateMinor, $minutes, 60);
    }

    private function percent(int $basisMinor, float $rate): int
    {
        return (int) round($basisMinor * $rate / 100, 0, PHP_ROUND_HALF_UP);
    }
}
