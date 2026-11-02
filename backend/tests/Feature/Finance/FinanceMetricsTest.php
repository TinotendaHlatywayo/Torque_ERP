<?php

namespace Tests\Feature\Finance;

use App\Models\Branch;
use App\Models\PaymentAccount;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class FinanceMetricsTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->admin = $this->user('Admin');
        $this->membership($this->branchA, $this->admin, 'admin');
    }

    public function test_finance_metrics_return_expected_formula_values(): void
    {
        PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/finance/metrics")
            ->assertOk()
            ->assertJsonPath('data.money_in_minor', 0)
            ->assertJsonPath('data.money_out_minor', 0)
            ->assertJsonPath('data.invoiced_revenue_minor', 0)
            ->assertJsonPath('data.money_owed_to_us_minor', 0)
            ->assertJsonPath('data.bills_to_pay_minor', 0)
            ->assertJsonPath('data.inventory_value_minor', 0)
            ->assertJsonPath('data.cash_position_minor', 0);
    }
}
