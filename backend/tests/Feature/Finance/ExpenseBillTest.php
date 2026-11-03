<?php

namespace Tests\Feature\Finance;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\PaymentAccount;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class ExpenseBillTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');

        $this->admin = $this->user('Admin');
        $this->cashier = $this->user('Cashier');

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->cashier, 'cashier');
    }

    public function test_expense_can_be_created_as_incurred_and_paid_later(): void
    {
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/expenses", [
                'category' => 'Utilities',
                'description' => 'Electricity',
                'amount_minor' => 1200,
                'currency_code' => 'USD',
                'incurred_date' => '2026-10-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'incurred');

        $expense = Expense::where('branch_id', $this->branchA->id)->firstOrFail();

        $account = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/expenses/{$expense->id}/pay", [
                'payment_account_id' => $account->id,
                'paid_date' => '2026-10-05',
                'reference' => 'exp-1',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $expense->refresh();
        $this->assertSame('paid', $expense->status);
        $this->assertSame('2026-10-05', $expense->paid_date->toDateString());

        $this->assertDatabaseHas('money_movements', [
            'branch_id' => $this->branchA->id,
            'source' => 'expense',
            'source_id' => $expense->id,
            'amount_minor' => 1200,
            'direction' => 'out',
        ]);
    }

    public function test_paid_expense_is_distinguished_from_incurred(): void
    {
        Expense::create([
            'branch_id' => $this->branchA->id,
            'category' => 'Rent',
            'description' => 'Office rent',
            'amount_minor' => 2000,
            'currency_code' => 'USD',
            'incurred_date' => '2026-10-01',
            'status' => 'incurred',
            'actor_id' => $this->admin->id,
        ]);

        Expense::create([
            'branch_id' => $this->branchA->id,
            'category' => 'Rent',
            'description' => 'Paid rent',
            'amount_minor' => 2000,
            'currency_code' => 'USD',
            'incurred_date' => '2026-10-01',
            'paid_date' => '2026-10-02',
            'status' => 'paid',
            'actor_id' => $this->admin->id,
        ]);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/expenses?status=incurred")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/expenses?status=paid")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_supplier_bill_payment_and_ageing(): void
    {
        $bill = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/supplier-bills", [
                'supplier_name' => 'Supplier One',
                'bill_number' => 'INV-1',
                'amount_minor' => 5000,
                'currency_code' => 'USD',
                'bill_date' => '2026-10-01',
                'due_date' => '2026-09-15',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $billId = $bill->json('data.id');

        $account = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Bank',
            'type' => 'bank',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/supplier-bills/{$billId}/pay", [
                'payment_account_id' => $account->id,
                'amount_minor' => 2000,
                'paid_at' => '2026-10-05',
                'reference' => 'pay-1',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'partial')
            ->assertJsonPath('data.paid_minor', 2000);

        $this->assertDatabaseHas('supplier_bill_payments', [
            'supplier_bill_id' => $billId,
            'amount_minor' => 2000,
        ]);

        $ageing = $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/bills-to-pay/ageing?as_of=2026-10-10")
            ->assertOk()
            ->json('data');

        $this->assertSame(3000, $ageing['days_1_30'] + $ageing['days_31_60'] + $ageing['days_61_90'] + $ageing['days_over_90']);
    }

    public function test_expense_and_bill_permission_denial(): void
    {
        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/expenses")
            ->assertForbidden();

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/supplier-bills")
            ->assertForbidden();

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/bills-to-pay/ageing")
            ->assertForbidden();
    }
}
