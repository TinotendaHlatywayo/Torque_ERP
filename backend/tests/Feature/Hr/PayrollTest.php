<?php

namespace Tests\Feature\Hr;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Expense;
use App\Models\MoneyMovement;
use App\Models\PaymentAccount;
use App\Models\PayrollAccrual;
use App\Models\PayrollPayment;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\SalaryGrade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Branch $branch;

    private User $admin;

    private User $manager;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->platform();
        $this->branch = $this->branch($platform, 'PAY');

        $this->admin = $this->user('Payroll Admin');
        $this->manager = $this->user('Payroll Manager');
        $this->approver = $this->user('Payroll Approver');

        $this->membership($this->branch, $this->admin, 'admin');
        $this->membership($this->branch, $this->manager, 'accountant', true, [
            'branch.payroll.view',
            'branch.payroll.manage',
        ]);
        $this->membership($this->branch, $this->approver, 'viewer', true, [
            'branch.payroll.view',
            'branch.payroll.approve',
        ]);
    }

    public function test_salary_grade_assignment_components_statutory_and_loan_produce_deterministic_payslip(): void
    {
        $employee = $this->employee('EMP-001', 'Paid Employee', '2025-01-01');

        $grade = $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/salary-grades", [
                'name' => 'Monthly standard',
                'code' => 'monthly',
                'pay_basis' => 'monthly',
                'base_rate_minor' => 300000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'MONTHLY')
            ->assertJsonPath('data.currency_code', 'USD');

        $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/employee-salary-assignments", [
                'employee_id' => $employee->id,
                'salary_grade_id' => $grade->json('data.id'),
                'effective_from' => '2025-01-01',
            ])
            ->assertCreated();

        $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/employee-pay-components", [
                'employee_id' => $employee->id,
                'type' => 'allowance',
                'code' => 'HOUSING',
                'label' => 'Housing allowance',
                'amount_minor' => 50000,
                'effective_from' => '2025-01-01',
            ])
            ->assertCreated();

        $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/employee-pay-components", [
                'employee_id' => $employee->id,
                'type' => 'deduction',
                'code' => 'WELFARE',
                'label' => 'Welfare deduction',
                'amount_minor' => 10000,
                'effective_from' => '2025-01-01',
            ])
            ->assertCreated();

        $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/payroll-statutory-rules", [
                'name' => 'Configured employee levy',
                'code' => 'LEVY',
                'applies_to' => 'gross',
                'employee_rate' => 10,
                'employer_rate' => 5,
                'effective_from' => '2025-01-01',
            ])
            ->assertCreated();

        $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/employee-loans", [
                'employee_id' => $employee->id,
                'principal_minor' => 100000,
                'installment_minor' => 25000,
                'issued_date' => '2025-02-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.balance_minor', 100000);

        $period = $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/payroll-periods", [
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-30',
                'pay_date' => '2026-06-30',
            ])
            ->assertCreated()
            ->json('data');

        $run = $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/payroll-periods/{$period['id']}/run")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.payslips.0.base_pay_minor', 300000)
            ->assertJsonPath('data.payslips.0.allowances_minor', 50000)
            ->assertJsonPath('data.payslips.0.gross_minor', 350000)
            ->assertJsonPath('data.payslips.0.statutory_employee_minor', 35000)
            ->assertJsonPath('data.payslips.0.loan_deductions_minor', 25000)
            ->assertJsonPath('data.payslips.0.deductions_minor', 70000)
            ->assertJsonPath('data.payslips.0.net_minor', 280000)
            ->assertJsonPath('data.payslips.0.employer_statutory_minor', 17500)
            ->json('data');

        $this->assertSame(350000, $run['gross_minor']);
        $this->assertSame(280000, $run['net_minor']);
        $this->assertDatabaseHas('payslip_lines', [
            'payslip_id' => $run['payslips'][0]['id'],
            'category' => 'loan',
            'amount_minor' => 25000,
        ]);
    }

    public function test_hourly_grade_uses_attendance_overtime_and_proration_window(): void
    {
        $hourly = $this->employee('EMP-002', 'Hourly Worker', '2025-01-01');
        $prorated = $this->employee('EMP-003', 'Prorated Worker', '2026-06-15');

        $hourlyGrade = SalaryGrade::create([
            'branch_id' => $this->branch->id,
            'name' => 'Hourly',
            'code' => 'HOURLY',
            'pay_basis' => SalaryGrade::BASIS_HOURLY,
            'base_rate_minor' => 2000,
            'overtime_multiplier' => 1.5,
            'currency_code' => 'USD',
        ]);
        $monthlyGrade = SalaryGrade::create([
            'branch_id' => $this->branch->id,
            'name' => 'Monthly',
            'code' => 'MONTHLY',
            'pay_basis' => SalaryGrade::BASIS_MONTHLY,
            'base_rate_minor' => 300000,
            'currency_code' => 'USD',
        ]);

        $this->assign($hourly, $hourlyGrade);
        $this->assign($prorated, $monthlyGrade);

        AttendanceRecord::create([
            'branch_id' => $this->branch->id,
            'employee_id' => $hourly->id,
            'work_date' => '2026-06-01',
            'status' => 'present',
            'scheduled_minutes' => 480,
            'worked_minutes' => 480,
            'overtime_minutes' => 60,
        ]);
        AttendanceRecord::create([
            'branch_id' => $this->branch->id,
            'employee_id' => $hourly->id,
            'work_date' => '2026-06-02',
            'status' => 'present',
            'scheduled_minutes' => 480,
            'worked_minutes' => 240,
            'overtime_minutes' => 0,
        ]);

        $period = $this->period('2026-06-01', '2026-06-30');

        $run = $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/payroll-periods/{$period->id}/run")
            ->assertCreated()
            ->json('data');

        $hourlySlip = collect($run['payslips'])->firstWhere('employee.employee_number', 'EMP-002');
        $proratedSlip = collect($run['payslips'])->firstWhere('employee.employee_number', 'EMP-003');

        $this->assertSame(24000, $hourlySlip['base_pay_minor']);
        $this->assertSame(3000, $hourlySlip['overtime_minor']);
        $this->assertSame(720, $hourlySlip['regular_minutes']);
        $this->assertSame(60, $hourlySlip['overtime_minutes']);
        $this->assertSame(22, $proratedSlip['period_working_days']);
        $this->assertSame(12, $proratedSlip['payable_working_days']);
        $this->assertSame(163636, $proratedSlip['base_pay_minor']);
    }

    public function test_approval_locks_period_and_applies_loan_repayment_once(): void
    {
        $employee = $this->employee('EMP-004', 'Loan Employee', '2025-01-01');
        $grade = $this->monthlyGrade();
        $this->assign($employee, $grade);

        $loan = EmployeeLoan::create([
            'branch_id' => $this->branch->id,
            'employee_id' => $employee->id,
            'principal_minor' => 50000,
            'balance_minor' => 50000,
            'installment_minor' => 20000,
            'status' => EmployeeLoan::STATUS_ACTIVE,
            'issued_date' => '2026-01-01',
        ]);

        $period = $this->period('2026-06-01', '2026-06-30');
        $run = $this->generate($period);

        $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/payroll-runs/{$run['id']}/approve")
            ->assertForbidden();

        $this->withAuthToken($this->approver)
            ->postJson("/api/branches/{$this->branch->id}/payroll-runs/{$run['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.period.status', 'locked');

        $this->assertSame(30000, $loan->refresh()->balance_minor);
        $this->assertSame(1, $loan->repayments()->count());
        $this->assertDatabaseHas('payroll_periods', ['id' => $period->id, 'status' => 'locked']);

        $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/payroll-periods/{$period->id}/run")
            ->assertUnprocessable();

        $this->withAuthToken($this->approver)
            ->postJson("/api/branches/{$this->branch->id}/payroll-runs/{$run['id']}/approve")
            ->assertUnprocessable();

        $this->assertSame(1, $loan->repayments()->count());
    }

    public function test_adjustments_apply_to_next_open_period_and_reversal_nets_out(): void
    {
        $employee = $this->employee('EMP-005', 'Adjustment Employee', '2025-01-01');
        $this->assign($employee, $this->monthlyGrade());

        $june = $this->period('2026-06-01', '2026-06-30');
        $juneRun = $this->generate($june);
        $junePayslip = Payslip::findOrFail($juneRun['payslips'][0]['id']);

        $this->withAuthToken($this->approver)
            ->postJson("/api/branches/{$this->branch->id}/payroll-runs/{$juneRun['id']}/approve")
            ->assertOk();

        $adjustment = $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/payslips/{$junePayslip->id}/adjustments", [
                'type' => 'earning',
                'label' => 'Missed overtime correction',
                'amount_minor' => 5000,
                'reason' => 'Approved overtime was omitted from June.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->json('data');

        $july = $this->period('2026-07-01', '2026-07-31');
        $julyRun = $this->generate($july);

        $this->assertSame(305000, $julyRun['gross_minor']);
        $this->assertSame(305000, $julyRun['net_minor']);
        $this->assertDatabaseHas('payroll_adjustments', [
            'id' => $adjustment['id'],
            'status' => 'pending',
        ]);

        $this->withAuthToken($this->approver)
            ->postJson("/api/branches/{$this->branch->id}/payroll-runs/{$julyRun['id']}/approve")
            ->assertOk();

        $this->assertDatabaseHas('payroll_adjustments', [
            'id' => $adjustment['id'],
            'status' => 'applied',
            'applied_run_id' => $julyRun['id'],
        ]);

        $reversal = $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/payroll-adjustments/{$adjustment['id']}/reverse")
            ->assertCreated()
            ->assertJsonPath('data.type', 'deduction')
            ->assertJsonPath('data.status', 'pending')
            ->json('data');

        $august = $this->period('2026-08-01', '2026-08-31');
        $augustRun = $this->generate($august);

        $this->assertSame(300000, $augustRun['gross_minor']);
        $this->assertSame(295000, $augustRun['net_minor']);
        $this->assertDatabaseHas('payroll_adjustments', [
            'id' => $reversal['id'],
            'status' => 'pending',
            'reverses_adjustment_id' => $adjustment['id'],
        ]);
    }

    public function test_payroll_is_branch_isolated_and_permission_gated(): void
    {
        $other = $this->branch($this->platform(), 'OTH');
        $otherAdmin = $this->user('Other Admin');
        $this->membership($other, $otherAdmin, 'admin');

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$other->id}/payroll-periods")
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branch->id}/payroll-periods")
            ->assertOk();

        $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/salary-grades", [
                'name' => 'Manager grade',
                'code' => 'MGR',
                'pay_basis' => 'monthly',
                'base_rate_minor' => 100000,
            ])
            ->assertCreated();

        $this->withAuthToken($this->approver)
            ->postJson("/api/branches/{$this->branch->id}/salary-grades", [
                'name' => 'Approver grade',
                'code' => 'APR',
                'pay_basis' => 'monthly',
                'base_rate_minor' => 100000,
            ])
            ->assertForbidden();

        $period = $this->period('2026-06-01', '2026-06-30');
        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$other->id}/payroll-periods/{$period->id}")
            ->assertNotFound();
    }

    public function test_payment_allocation_posts_cash_movement_and_reconciles_without_duplicate_expense(): void
    {
        $employee = $this->employee('EMP-001', 'Paid Employee', '2025-01-01');
        $this->assign($employee, $this->monthlyGrade());
        $period = $this->period('2025-01-01', '2025-01-31');
        $runData = $this->generate($period);

        $this->withAuthToken($this->approver)
            ->postJson("/api/branches/{$this->branch->id}/payroll-runs/{$runData['id']}/approve")
            ->assertOk();

        $this->assertSame(1, PayrollAccrual::where('branch_id', $this->branch->id)->count());
        $this->assertSame(1, Expense::where('branch_id', $this->branch->id)->where('category', 'Payroll')->count());
        $this->assertSame(300000, (int) Expense::where('category', 'Payroll')->value('amount_minor'));

        $account = PaymentAccount::create([
            'branch_id' => $this->branch->id,
            'name' => 'Payroll bank',
            'type' => 'bank',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);
        $idempotencyKey = (string) Str::uuid();

        $first = $this->withAuthToken($this->manager)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson("/api/branches/{$this->branch->id}/payroll-runs/{$runData['id']}/payments", [
                'payment_account_id' => $account->id,
                'amount_minor' => 100000,
                'paid_at' => '2025-01-31T10:00:00Z',
                'method' => 'bank',
                'reference' => 'BATCH-1',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withAuthToken($this->manager)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson("/api/branches/{$this->branch->id}/payroll-runs/{$runData['id']}/payments", [
                'payment_account_id' => $account->id,
                'amount_minor' => 100000,
                'paid_at' => '2025-01-31T10:00:00Z',
                'method' => 'bank',
                'reference' => 'BATCH-1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.id', $first);

        $this->assertSame(1, PayrollPayment::where('branch_id', $this->branch->id)->count());
        $this->assertSame(1, MoneyMovement::where('source', 'payroll')->where('source_id', $runData['id'])->count());
        $this->assertSame('out', MoneyMovement::where('source', 'payroll')->value('direction'));

        $this->withAuthToken($this->manager)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branch->id}/payroll-runs/{$runData['id']}/payments", [
                'payment_account_id' => $account->id,
                'amount_minor' => 250000,
                'paid_at' => '2025-01-31T11:00:00Z',
                'method' => 'bank',
            ])
            ->assertUnprocessable();

        $reconciliation = $this->withAuthToken($this->approver)
            ->getJson("/api/branches/{$this->branch->id}/payroll-runs/{$runData['id']}/reconciliation")
            ->assertOk()
            ->assertJsonPath('data.status', 'PASS')
            ->json('data');

        $this->assertSame(100000, $reconciliation['payments_minor']['allocated']);
        $this->assertSame(200000, $reconciliation['payments_minor']['outstanding']);
        $this->assertSame(300000, $reconciliation['accrual_minor']);

        $this->withAuthToken($this->approver)
            ->get("/api/branches/{$this->branch->id}/payroll-runs/{$runData['id']}/payslips/export")
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->get("/api/branches/{$this->branch->id}/payroll-runs/{$runData['id']}/payslips/export")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    }

    public function test_payroll_employee_options_are_visible_to_payroll_viewer_and_exclude_ineligible_statuses(): void
    {
        $this->employee('EMP-001', 'Active Employee', '2025-01-01');

        $terminated = $this->employee('EMP-002', 'Terminated Employee', '2025-01-01');
        $terminated->update(['status' => Employee::STATUS_TERMINATED]);

        $suspended = $this->employee('EMP-003', 'Suspended Employee', '2025-01-01');
        $suspended->update(['status' => Employee::STATUS_SUSPENDED]);

        $this->withAuthToken($this->approver)
            ->getJson("/api/branches/{$this->branch->id}/payroll-employees")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.employee_number', 'EMP-001')
            ->assertJsonPath('data.1.employee_number', 'EMP-002');

        $this->withAuthToken($this->user('No payroll access'))
            ->getJson("/api/branches/{$this->branch->id}/payroll-employees")
            ->assertForbidden();
    }

    private function employee(string $number, string $name, string $hireDate): Employee
    {
        [$first, $last] = explode(' ', $name, 2);

        return Employee::create([
            'branch_id' => $this->branch->id,
            'employee_number' => $number,
            'first_name' => $first,
            'last_name' => $last,
            'hire_date' => $hireDate,
            'status' => Employee::STATUS_ACTIVE,
        ]);
    }

    private function monthlyGrade(): SalaryGrade
    {
        return SalaryGrade::create([
            'branch_id' => $this->branch->id,
            'name' => 'Monthly',
            'code' => 'MONTHLY-'.uniqid(),
            'pay_basis' => SalaryGrade::BASIS_MONTHLY,
            'base_rate_minor' => 300000,
            'currency_code' => 'USD',
        ]);
    }

    private function assign(Employee $employee, SalaryGrade $grade): void
    {
        $employee->salaryAssignments()->create([
            'branch_id' => $this->branch->id,
            'salary_grade_id' => $grade->id,
            'effective_from' => '2025-01-01',
        ]);
    }

    private function period(string $start, string $end): PayrollPeriod
    {
        return PayrollPeriod::create([
            'branch_id' => $this->branch->id,
            'start_date' => $start,
            'end_date' => $end,
            'pay_date' => $end,
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
    }

    private function generate(PayrollPeriod $period): array
    {
        return $this->withAuthToken($this->manager)
            ->postJson("/api/branches/{$this->branch->id}/payroll-periods/{$period->id}/run")
            ->assertCreated()
            ->json('data');
    }
}
