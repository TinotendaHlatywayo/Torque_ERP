<?php

namespace Tests\Feature\Hr;

use App\Models\Branch;
use App\Models\PaymentAccount;
use App\Models\PayrollPayment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use App\Support\PayrollEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class PayrollConcurrencyTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    public function test_stale_draft_run_is_rechecked_inside_approval_transaction(): void
    {
        [$branch, $approver] = $this->payrollContext();
        $period = $this->period($branch);
        $run = PayrollRun::create([
            'branch_id' => $branch->id,
            'payroll_period_id' => $period->id,
            'kind' => PayrollRun::KIND_REGULAR,
            'version' => 1,
            'status' => PayrollRun::STATUS_DRAFT,
            'net_minor' => 100000,
            'generated_by' => $approver->id,
        ]);

        $staleRun = PayrollRun::findOrFail($run->id);
        PayrollRun::whereKey($run->id)->update(['status' => PayrollRun::STATUS_APPROVED]);

        try {
            app(PayrollEngine::class)->approve($staleRun, $approver->id);
            $this->fail('Expected approval of a stale draft run to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('run', $exception->errors());
        }

        $this->assertDatabaseHas('payroll_runs', [
            'id' => $run->id,
            'status' => PayrollRun::STATUS_APPROVED,
        ]);
        $this->assertDatabaseHas('payroll_periods', [
            'id' => $period->id,
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
    }

    public function test_payment_key_race_returns_conflict_instead_of_server_error(): void
    {
        [$branch, $manager, $account] = $this->payrollContext();
        $period = $this->period($branch);
        $run = PayrollRun::create([
            'branch_id' => $branch->id,
            'payroll_period_id' => $period->id,
            'kind' => PayrollRun::KIND_REGULAR,
            'version' => 1,
            'status' => PayrollRun::STATUS_APPROVED,
            'net_minor' => 300000,
            'generated_by' => $manager->id,
        ]);

        $key = (string) Str::uuid();

        PayrollPayment::creating(function () use ($branch, $run, $account, $key, $manager): void {
            DB::table('payroll_payments')->insert([
                'id' => (string) Str::ulid(),
                'branch_id' => $branch->id,
                'payroll_run_id' => $run->id,
                'payment_account_id' => $account->id,
                'amount_minor' => 100000,
                'currency_code' => 'USD',
                'paid_at' => now(),
                'method' => 'bank',
                'status' => PayrollPayment::STATUS_RECORDED,
                'idempotency_key' => $key,
                'request_hash' => str_repeat('0', 64),
                'actor_id' => $manager->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->withAuthToken($manager)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/branches/{$branch->id}/payroll-runs/{$run->id}/payments", [
                'payment_account_id' => $account->id,
                'amount_minor' => 100000,
                'paid_at' => '2026-11-20T10:00:00Z',
                'method' => 'bank',
            ])
            ->assertStatus(409);

        $this->assertSame(0, PayrollPayment::where('branch_id', $branch->id)->count());
    }

    /**
     * @return array{0: Branch, 1: User, 2: PaymentAccount}
     */
    private function payrollContext(): array
    {
        $branch = $this->branch($this->platform(), 'PAY');
        $user = $this->user('Payroll Manager');
        $this->membership($branch, $user, 'accountant', true, [
            'branch.payroll.view',
            'branch.payroll.manage',
            'branch.payroll.approve',
        ]);

        $account = PaymentAccount::create([
            'branch_id' => $branch->id,
            'name' => 'Payroll bank',
            'type' => 'bank',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        return [$branch, $user, $account];
    }

    private function period(Branch $branch): PayrollPeriod
    {
        return PayrollPeriod::create([
            'branch_id' => $branch->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'pay_date' => '2026-11-30',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
    }
}
