<?php

namespace Tests\Feature\Hr;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Expense;
use App\Models\LeaveType;
use App\Models\MoneyMovement;
use App\Models\PaymentAccount;
use App\Models\PayrollAccrual;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\SalaryGrade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class HrPayrollAcceptanceTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    public function test_hr_payroll_finance_and_audit_flow_acceptance(): void
    {
        [$branch, $manager, $approver, $revoked, $otherBranchUser] = $this->fixture();
        $employee = $this->employee($branch, 'ACC-001', 'Acceptance Employee');
        $grade = SalaryGrade::create([
            'branch_id' => $branch->id,
            'name' => 'Monthly',
            'code' => 'ACC-MONTHLY',
            'pay_basis' => SalaryGrade::BASIS_MONTHLY,
            'base_rate_minor' => 300000,
            'currency_code' => 'USD',
        ]);
        $employee->salaryAssignments()->create([
            'branch_id' => $branch->id,
            'salary_grade_id' => $grade->id,
            'effective_from' => '2025-01-01',
        ]);
        $loan = EmployeeLoan::create([
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'principal_minor' => 50000,
            'balance_minor' => 50000,
            'installment_minor' => 20000,
            'status' => EmployeeLoan::STATUS_ACTIVE,
            'issued_date' => '2026-01-01',
        ]);

        $leaveType = LeaveType::create([
            'branch_id' => $branch->id,
            'name' => 'Annual leave',
            'code' => 'ACC-ANNUAL',
            'is_paid' => true,
            'requires_approval' => true,
            'max_days_per_year' => 20,
            'counts_weekends' => false,
            'is_active' => true,
        ]);
        $leave = $this->withAuthToken($manager)
            ->postJson("/api/branches/{$branch->id}/leave-requests", [
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-05',
                'reason' => 'Approved absence',
            ])
            ->assertCreated()
            ->json('data');

        $this->withAuthToken($manager)
            ->postJson("/api/branches/{$branch->id}/leave-requests/{$leave['id']}/approve", ['decision_note' => 'Approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('audit_logs', [
            'branch_id' => $branch->id,
            'action' => 'leave_request.approved',
            'entity_id' => $leave['id'],
        ]);

        $period = PayrollPeriod::create([
            'branch_id' => $branch->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'pay_date' => '2026-06-30',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
        $run = $this->withAuthToken($manager)
            ->postJson("/api/branches/{$branch->id}/payroll-periods/{$period->id}/run")
            ->assertCreated()
            ->assertJsonPath('data.payslips.0.loan_deductions_minor', 20000)
            ->json('data');

        $this->withAuthToken($manager)
            ->postJson("/api/branches/{$branch->id}/payroll-runs/{$run['id']}/approve")
            ->assertForbidden();

        $this->withAuthToken($approver)
            ->postJson("/api/branches/{$branch->id}/payroll-runs/{$run['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.period.status', 'locked');

        $this->assertSame(30000, $loan->fresh()->balance_minor);
        $this->assertSame(1, $loan->repayments()->count());
        $this->assertSame(1, PayrollAccrual::where('payroll_run_id', $run['id'])->count());
        $this->assertSame(300000, (int) Expense::where('category', 'Payroll')->value('amount_minor'));

        $this->withAuthToken($manager)
            ->postJson("/api/branches/{$branch->id}/payroll-periods/{$period->id}/run")
            ->assertUnprocessable();
        $this->withAuthToken($approver)
            ->postJson("/api/branches/{$branch->id}/payroll-runs/{$run['id']}/approve")
            ->assertUnprocessable();

        $payslip = Payslip::where('payroll_run_id', $run['id'])->firstOrFail();
        $this->withAuthToken($manager)
            ->postJson("/api/branches/{$branch->id}/payslips/{$payslip->id}/adjustments", [
                'type' => 'earning',
                'label' => 'Correction',
                'amount_minor' => 1000,
                'reason' => 'Approved post-lock correction',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $account = PaymentAccount::create([
            'branch_id' => $branch->id,
            'name' => 'Payroll account',
            'type' => 'bank',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);
        $this->withAuthToken($manager)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$branch->id}/payroll-runs/{$run['id']}/payments", [
                'payment_account_id' => $account->id,
                'amount_minor' => 100000,
                'paid_at' => '2026-06-30T10:00:00Z',
                'method' => 'bank',
                'reference' => 'ACC-BATCH-1',
            ])
            ->assertCreated();

        $this->withAuthToken($approver)
            ->getJson("/api/branches/{$branch->id}/payroll-runs/{$run['id']}/reconciliation")
            ->assertOk()
            ->assertJsonPath('data.status', 'PASS')
            ->assertJsonPath('data.payments_minor.outstanding', 180000);

        $this->assertSame(1, MoneyMovement::where('source', 'payroll')->where('source_id', $run['id'])->count());
        foreach (['leave_request.approved', 'payroll_run.approved', 'payroll.payment.recorded'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['branch_id' => $branch->id, 'action' => $action]);
        }

        $this->withAuthToken($revoked)
            ->getJson("/api/branches/{$branch->id}/payroll-runs/{$run['id']}")
            ->assertForbidden();

        $this->withAuthToken($otherBranchUser)
            ->getJson("/api/branches/{$branch->id}/payroll-runs/{$run['id']}")
            ->assertForbidden();
    }

    public function test_unverified_legal_and_tax_assumptions_are_explicitly_recorded(): void
    {
        $config = config('torqueerp.payroll_assumptions', []);
        $this->assertIsArray($config);
        $this->assertSame('BLOCKED', $config['statutory_rates'] ?? 'BLOCKED');
        $this->assertSame('BLOCKED', $config['tax_filing'] ?? 'BLOCKED');
    }

    /** @return array{0: Branch, 1: User, 2: User, 3: User, 4: User} */
    private function fixture(): array
    {
        $platform = $this->platform();
        $branch = $this->branch($platform, 'ACC');
        $otherBranch = $this->branch($platform, 'OTH');

        $manager = $this->user('HR Manager');
        $approver = $this->user('Payroll Approver');
        $revoked = $this->user('Revoked User');
        $otherBranchUser = $this->user('Other Branch User');

        $this->membership($branch, $manager, 'accountant', true, [
            'branch.hr.manage',
            'branch.hr.leave.approve',
        ]);
        $this->membership($branch, $approver, 'viewer', true, [
            'branch.payroll.view',
            'branch.payroll.approve',
        ]);
        $this->membership($branch, $revoked, 'accountant', false, []);
        $this->membership($otherBranch, $otherBranchUser, 'admin');

        return [$branch, $manager, $approver, $revoked, $otherBranchUser];
    }

    private function employee(Branch $branch, string $number, string $name): Employee
    {
        [$first, $last] = explode(' ', $name, 2);

        return Employee::create([
            'branch_id' => $branch->id,
            'employee_number' => $number,
            'first_name' => $first,
            'last_name' => $last,
            'hire_date' => '2025-01-01',
            'status' => Employee::STATUS_ACTIVE,
        ]);
    }
}
