<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\MoneyMovement;
use App\Models\PaymentAccount;
use App\Models\Platform;
use App\Models\User;
use App\Support\MoneyMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class AssetManagementTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->branchB = $this->branch($this->platform, 'BTB');

        $this->admin = $this->user('Admin');
        $this->cashier = $this->user('Cashier');

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->cashier, 'cashier');
    }

    public function test_acquisition_links_existing_purchase_without_creating_duplicate_expense(): void
    {
        $expense = $this->expense($this->branchA, 'Purchase invoice', 150000);
        $expenseCount = Expense::where('branch_id', $this->branchA->id)->count();

        $response = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/assets", [
                'name' => 'Delivery van',
                'description' => 'Purchase linked to an existing expense',
                'acquisition_date' => '2025-01-15',
                'cost_minor' => 150000,
                'currency_code' => 'USD',
                'location' => 'Main depot',
                'custodian' => 'Transport manager',
                'source_type' => 'expense',
                'source_id' => $expense->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.source_type', 'expense')
            ->assertJsonPath('data.source_id', $expense->id)
            ->assertJsonPath('data.status', 'active');

        $assetId = $response->json('data.id');

        $this->assertDatabaseHas('assets', [
            'id' => $assetId,
            'branch_id' => $this->branchA->id,
            'cost_minor' => 150000,
            'location' => 'Main depot',
            'custodian' => 'Transport manager',
            'source_type' => 'expense',
            'source_id' => $expense->id,
        ]);
        $this->assertSame($expenseCount, Expense::where('branch_id', $this->branchA->id)->count());
        $this->assertSame(0, MoneyMovement::where('branch_id', $this->branchA->id)->count());
        $this->assertDatabaseHas('audit_logs', [
            'branch_id' => $this->branchA->id,
            'action' => 'asset.acquired',
            'entity_type' => Asset::class,
            'entity_id' => $assetId,
        ]);
    }

    public function test_maintenance_is_recorded_with_optional_links_without_new_postings(): void
    {
        $asset = $this->asset($this->branchA, 'Forklift', 200000);
        $expense = $this->expense($this->branchA, 'Service expense', 5000);
        $payment = $this->payment($this->branchA, $this->admin, 5000);
        $expenseCount = Expense::where('branch_id', $this->branchA->id)->count();
        $movementCount = MoneyMovement::where('branch_id', $this->branchA->id)->count();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/assets/{$asset->id}/maintenances", [
                'performed_date' => '2026-02-10',
                'cost_minor' => 5000,
                'currency_code' => 'USD',
                'provider' => 'Local workshop',
                'description' => 'Annual service and inspection',
                'expense_id' => $expense->id,
                'payment_id' => $payment->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'Local workshop')
            ->assertJsonPath('data.expense_id', $expense->id)
            ->assertJsonPath('data.payment_id', $payment->id);

        $this->assertDatabaseHas('asset_maintenances', [
            'asset_id' => $asset->id,
            'branch_id' => $this->branchA->id,
            'cost_minor' => 5000,
            'provider' => 'Local workshop',
            'description' => 'Annual service and inspection',
            'expense_id' => $expense->id,
            'payment_id' => $payment->id,
        ]);
        $this->assertSame($expenseCount, Expense::where('branch_id', $this->branchA->id)->count());
        $this->assertSame($movementCount, MoneyMovement::where('branch_id', $this->branchA->id)->count());

        $detail = $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/assets/{$asset->id}")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $detail['maintenances']);
        $this->assertSame('Annual service and inspection', $detail['maintenances'][0]['description']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'asset.maintenance_recorded',
            'entity_type' => AssetMaintenance::class,
        ]);
    }

    public function test_disposal_is_audited_and_returns_estimated_financial_effect_without_posting(): void
    {
        $asset = $this->asset($this->branchA, 'Compressor', 100000);
        $expenseCount = Expense::where('branch_id', $this->branchA->id)->count();
        $movementCount = MoneyMovement::where('branch_id', $this->branchA->id)->count();

        $response = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/assets/{$asset->id}/dispose", [
                'disposal_date' => '2026-06-30',
                'proceeds_minor' => 90000,
                'accumulated_estimate_minor' => 20000,
                'notes' => 'Sold as-is to a licensed dealer',
            ])
            ->assertOk()
            ->assertJsonPath('data.asset.status', 'disposed')
            ->assertJsonPath('data.financial_effect.cost_minor', 100000)
            ->assertJsonPath('data.financial_effect.accumulated_estimate_minor', 20000)
            ->assertJsonPath('data.financial_effect.book_value_estimate_minor', 80000)
            ->assertJsonPath('data.financial_effect.proceeds_minor', 90000)
            ->assertJsonPath('data.financial_effect.estimated_gain_loss_minor', 10000)
            ->assertJsonPath('data.financial_effect.label', 'ESTIMATE')
            ->assertJsonPath('data.financial_effect.accounting_use', 'BLOCKED')
            ->assertJsonPath('data.financial_effect.automatic_entries_posted', 0);

        $this->assertNotNull($response->json('data.asset.id'));
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => 'disposed',
            'disposal_proceeds_minor' => 90000,
        ]);
        $this->assertSame('2026-06-30', $asset->refresh()->disposal_date->toDateString());
        $this->assertSame($expenseCount, Expense::where('branch_id', $this->branchA->id)->count());
        $this->assertSame($movementCount, MoneyMovement::where('branch_id', $this->branchA->id)->count());

        $audit = AuditLog::where('branch_id', $this->branchA->id)
            ->where('action', 'asset.disposed')
            ->where('entity_type', Asset::class)
            ->where('entity_id', $asset->id)
            ->firstOrFail();

        $this->assertSame('active', $audit->before['status']);
        $this->assertSame('disposed', $audit->after['status']);
        $this->assertSame(10000, $audit->after['financial_effect']['estimated_gain_loss_minor']);
        $this->assertSame('ESTIMATE', $audit->after['financial_effect']['label']);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/assets/{$asset->id}/dispose", [
                'disposal_date' => '2026-07-01',
                'proceeds_minor' => 0,
                'accumulated_estimate_minor' => 0,
            ])
            ->assertConflict();
    }

    public function test_depreciation_estimate_is_labelled_blocked_and_posts_nothing(): void
    {
        $asset = $this->asset($this->branchA, 'Generator', 120000, '2025-01-01');
        $expenseCount = Expense::where('branch_id', $this->branchA->id)->count();
        $movementCount = MoneyMovement::where('branch_id', $this->branchA->id)->count();
        $before = $asset->fresh()->toArray();

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/assets/{$asset->id}/depreciation-estimate?as_of=2026-01-01&useful_life_years=5")
            ->assertOk()
            ->assertJsonPath('data.label', 'ESTIMATE')
            ->assertJsonPath('data.accounting_use', 'BLOCKED')
            ->assertJsonPath('data.policy_status', 'unconfirmed')
            ->assertJsonPath('data.useful_life_months', 60)
            ->assertJsonPath('data.months_elapsed', 12)
            ->assertJsonPath('data.accumulated_estimate_minor', 24000)
            ->assertJsonPath('data.book_value_estimate_minor', 96000)
            ->assertJsonPath('data.posted', false)
            ->assertJsonPath('data.financial_movement_posted', false);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/assets/depreciation-estimate?as_of=2026-01-01&useful_life_years=5")
            ->assertOk()
            ->assertJsonPath('meta.label', 'ESTIMATE')
            ->assertJsonPath('meta.accounting_use', 'BLOCKED')
            ->assertJsonPath('meta.posted', false)
            ->assertJsonCount(1, 'data');

        $this->assertSame($expenseCount, Expense::where('branch_id', $this->branchA->id)->count());
        $this->assertSame($movementCount, MoneyMovement::where('branch_id', $this->branchA->id)->count());
        $this->assertSame($before, $asset->fresh()->toArray());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_duplicate_acquisition_link_is_rejected(): void
    {
        $expense = $this->expense($this->branchA, 'Duplicate purchase', 75000);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/assets", $this->assetPayload('First asset', $expense))
            ->assertCreated();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/assets", $this->assetPayload('Duplicate asset', $expense))
            ->assertConflict();

        $this->assertSame(1, Asset::where('branch_id', $this->branchA->id)->count());
    }

    public function test_assets_are_branch_scoped_and_permission_gated(): void
    {
        $asset = $this->asset($this->branchA, 'Branch A asset', 10000);
        $foreignAsset = $this->asset($this->branchB, 'Branch B asset', 10000);
        $foreignExpense = $this->expense($this->branchB, 'Foreign purchase', 10000);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchB->id}/assets")
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/assets/{$foreignAsset->id}")
            ->assertNotFound();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/assets", [
                ...$this->assetPayload('Bad link', null),
                'source_type' => 'expense',
                'source_id' => $foreignExpense->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('source_id');

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/assets")
            ->assertForbidden();

        $this->withAuthToken($this->cashier)
            ->postJson("/api/branches/{$this->branchA->id}/assets", $this->assetPayload('Denied', null))
            ->assertForbidden();

        $this->withAuthToken($this->cashier)
            ->postJson("/api/branches/{$this->branchA->id}/assets/{$asset->id}/dispose", [
                'disposal_date' => '2026-06-30',
                'proceeds_minor' => 0,
                'accumulated_estimate_minor' => 0,
            ])
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/assets")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * @return array<string, mixed>
     */
    private function assetPayload(string $name, ?Expense $expense): array
    {
        $payload = [
            'name' => $name,
            'acquisition_date' => '2025-02-01',
            'cost_minor' => 75000,
            'currency_code' => 'USD',
            'location' => 'Store room',
            'custodian' => 'Storekeeper',
        ];

        if ($expense) {
            $payload['source_type'] = 'expense';
            $payload['source_id'] = $expense->id;
        }

        return $payload;
    }

    private function expense(Branch $branch, string $description, int $amountMinor): Expense
    {
        return $branch->expenses()->create([
            'category' => 'Asset purchase',
            'description' => $description,
            'amount_minor' => $amountMinor,
            'currency_code' => 'USD',
            'incurred_date' => '2025-01-01',
            'paid_date' => '2025-01-01',
            'status' => 'paid',
            'actor_id' => $this->admin->id,
        ]);
    }

    private function asset(Branch $branch, string $name, int $costMinor, string $acquisitionDate = '2025-01-01'): Asset
    {
        return $branch->assets()->create([
            'name' => $name,
            'acquisition_date' => $acquisitionDate,
            'cost_minor' => $costMinor,
            'currency_code' => 'USD',
            'location' => 'Main depot',
            'custodian' => 'Transport manager',
            'status' => Asset::STATUS_ACTIVE,
            'actor_id' => $this->admin->id,
        ]);
    }

    private function payment(Branch $branch, User $actor, int $amountMinor): MoneyMovement
    {
        $account = PaymentAccount::create([
            'branch_id' => $branch->id,
            'name' => 'Bank',
            'type' => 'bank',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        return app(MoneyMovementService::class)->postMovement(
            $branch,
            $account,
            'adjustment_out',
            $amountMinor,
            'USD',
            'asset-maintenance-test',
            'Existing maintenance payment',
            null,
            null,
            $actor,
            'asset-maintenance-test:'.$branch->id,
        );
    }
}
