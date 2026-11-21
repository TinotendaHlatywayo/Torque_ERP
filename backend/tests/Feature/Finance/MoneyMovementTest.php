<?php

namespace Tests\Feature\Finance;

use App\Models\Branch;
use App\Models\MoneyMovement;
use App\Models\PaymentAccount;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class MoneyMovementTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $admin;

    private User $cashier;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->branchB = $this->branch($this->platform, 'BTB');

        $this->admin = $this->user('Admin');
        $this->cashier = $this->user('Cashier');
        $this->adminB = $this->user('Branch B Admin');

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->cashier, 'cashier');
        $this->membership($this->branchB, $this->adminB, 'admin');
    }

    public function test_payment_account_and_money_movement_can_be_created(): void
    {
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/payment-accounts", [
                'name' => 'Main Cash',
                'type' => 'cash',
                'currency_code' => 'USD',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Main Cash');

        $account = PaymentAccount::where('branch_id', $this->branchA->id)->firstOrFail();

        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/payment-accounts/{$account->id}/movements", [
                'movement_type' => 'opening',
                'amount_minor' => 5000,
                'currency_code' => 'USD',
                'reason' => 'Opening balance',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('money_movements', [
            'branch_id' => $this->branchA->id,
            'payment_account_id' => $account->id,
            'movement_type' => 'opening',
            'amount_minor' => 5000,
            'currency_code' => 'USD',
            'direction' => 'in',
        ]);
    }

    public function test_movements_without_idempotency_key_do_not_collide(): void
    {
        $account = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $firstId = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/payment-accounts/{$account->id}/movements", [
                'movement_type' => 'opening',
                'amount_minor' => 1000,
                'currency_code' => 'USD',
                'reason' => 'First opening',
            ])
            ->assertCreated()
            ->json('data.id');

        $secondId = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/payment-accounts/{$account->id}/movements", [
                'movement_type' => 'opening',
                'amount_minor' => 2000,
                'currency_code' => 'USD',
                'reason' => 'Second opening',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertNotSame($firstId, $secondId);
        $this->assertSame(2, MoneyMovement::where('branch_id', $this->branchA->id)->count());

        $keys = MoneyMovement::where('branch_id', $this->branchA->id)->pluck('idempotency_key')->all();

        $this->assertSame(2, count(array_unique($keys)));

        foreach ($keys as $key) {
            $this->assertNotSame('', trim((string) $key));
        }
    }

    public function test_transfer_creates_linked_paired_movements(): void
    {
        $cash = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);
        $bank = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Bank',
            'type' => 'bank',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $key = (string) Str::uuid();

        $response = $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/branches/{$this->branchA->id}/transfers", [
                'from_account_id' => $cash->id,
                'to_account_id' => $bank->id,
                'amount_minor' => 1200,
                'currency_code' => 'USD',
                'reason' => 'Bank deposit',
                'reference' => 'dep-1',
            ])
            ->assertCreated();

        $outgoingId = $response->json('data.outgoing_id');
        $incomingId = $response->json('data.incoming_id');
        $groupId = $response->json('data.transfer_group_id');

        $this->assertDatabaseHas('money_movements', [
            'id' => $outgoingId,
            'movement_type' => 'transfer_out',
            'direction' => 'out',
            'amount_minor' => 1200,
            'transfer_group_id' => $groupId,
        ]);

        $this->assertDatabaseHas('money_movements', [
            'id' => $incomingId,
            'movement_type' => 'transfer_in',
            'direction' => 'in',
            'amount_minor' => 1200,
            'transfer_group_id' => $groupId,
        ]);

        $this->assertSame(2, MoneyMovement::where('transfer_group_id', $groupId)->count());
    }

    public function test_transfer_is_idempotent(): void
    {
        $cash = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);
        $bank = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Bank',
            'type' => 'bank',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $key = (string) Str::uuid();
        $payload = [
            'from_account_id' => $cash->id,
            'to_account_id' => $bank->id,
            'amount_minor' => 500,
            'currency_code' => 'USD',
            'reason' => 'Deposit',
        ];

        $first = $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/branches/{$this->branchA->id}/transfers", $payload)
            ->assertCreated()
            ->json('data.outgoing_id');

        $second = $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/branches/{$this->branchA->id}/transfers", $payload)
            ->assertCreated()
            ->json('data.outgoing_id');

        $this->assertSame($first, $second);
        $this->assertSame(2, MoneyMovement::where('branch_id', $this->branchA->id)->count());
    }

    public function test_reversal_creates_opposite_movement(): void
    {
        $account = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $movement = $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/payment-accounts/{$account->id}/movements", [
                'movement_type' => 'opening',
                'amount_minor' => 3000,
                'currency_code' => 'USD',
                'reason' => 'Opening',
            ])
            ->assertCreated();

        $movementModel = MoneyMovement::where('branch_id', $this->branchA->id)->firstOrFail();

        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/money-movements/{$movementModel->id}/reverse", [
                'reason' => 'Correction',
            ])
            ->assertOk();

        $this->assertSame(2, MoneyMovement::where('branch_id', $this->branchA->id)->count());
        $this->assertDatabaseHas('money_movements', [
            'branch_id' => $this->branchA->id,
            'movement_type' => 'reversal',
            'direction' => 'out',
            'amount_minor' => 3000,
            'reverses_movement_id' => $movementModel->id,
        ]);
    }

    public function test_multi_currency_accounts_are_isolated(): void
    {
        $usd = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'USD Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);
        $eur = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'EUR Cash',
            'type' => 'cash',
            'currency_code' => 'EUR',
            'is_active' => true,
        ]);

        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/payment-accounts/{$usd->id}/movements", [
                'movement_type' => 'opening',
                'amount_minor' => 1000,
                'currency_code' => 'USD',
                'reason' => 'USD opening',
            ])
            ->assertCreated();

        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/payment-accounts/{$eur->id}/movements", [
                'movement_type' => 'opening',
                'amount_minor' => 2000,
                'currency_code' => 'EUR',
                'reason' => 'EUR opening',
            ])
            ->assertCreated();

        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/transfers", [
                'from_account_id' => $usd->id,
                'to_account_id' => $eur->id,
                'amount_minor' => 100,
                'currency_code' => 'USD',
                'reason' => 'Cross currency',
            ])
            ->assertUnprocessable();

        $this->assertSame(2, MoneyMovement::where('branch_id', $this->branchA->id)->count());
    }

    public function test_transfer_idempotency_keys_are_bounded_and_directional(): void
    {
        $cash = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);
        $bank = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Bank',
            'type' => 'bank',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);
        $key = (string) Str::uuid();

        $transfer = $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', $key)
            ->postJson("/api/branches/{$this->branchA->id}/transfers", [
                'from_account_id' => $cash->id,
                'to_account_id' => $bank->id,
                'amount_minor' => 1200,
                'currency_code' => 'USD',
                'reason' => 'Bounded keys',
            ])
            ->assertCreated()
            ->json('data');

        $keys = MoneyMovement::query()
            ->whereIn('id', [$transfer['outgoing_id'], $transfer['incoming_id']])
            ->pluck('idempotency_key', 'movement_type')
            ->all();

        $this->assertNotSame($key, $keys['transfer_out']);
        $this->assertStringEndsWith(':out', $keys['transfer_out']);
        $this->assertStringEndsWith(':in', $keys['transfer_in']);
        $this->assertLessThanOrEqual(100, strlen($keys['transfer_out']));
        $this->assertLessThanOrEqual(100, strlen($keys['transfer_in']));
    }

    public function test_reversal_idempotency_key_cannot_reverse_another_movement(): void
    {
        $account = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $first = $this->createMovement($account, 3000, 'First opening');
        $second = $this->createMovement($account, 4000, 'Second opening');

        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', 'shared-reversal-key')
            ->postJson("/api/branches/{$this->branchA->id}/money-movements/{$first->id}/reverse", [
                'reason' => 'Correction',
            ])
            ->assertOk();

        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', 'shared-reversal-key')
            ->postJson("/api/branches/{$this->branchA->id}/money-movements/{$second->id}/reverse", [
                'reason' => 'Correction',
            ])
            ->assertStatus(409);

        $this->assertSame(3, MoneyMovement::where('branch_id', $this->branchA->id)->count());
        $this->assertSame(1, MoneyMovement::where('movement_type', 'reversal')->count());
    }

    public function test_permission_denial_and_cross_branch_isolation(): void
    {
        $account = PaymentAccount::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Cash',
            'type' => 'cash',
            'currency_code' => 'USD',
            'is_active' => true,
        ]);

        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/payment-accounts")
            ->assertForbidden();

        $this->withAuthToken($this->adminB)
            ->getJson("/api/branches/{$this->branchB->id}/payment-accounts")
            ->assertOk();

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchB->id}/payment-accounts")
            ->assertForbidden();
    }

    private function createMovement(PaymentAccount $account, int $amountMinor, string $reason): MoneyMovement
    {
        $this->withAuthToken($this->admin)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/payment-accounts/{$account->id}/movements", [
                'movement_type' => 'opening',
                'amount_minor' => $amountMinor,
                'currency_code' => 'USD',
                'reason' => $reason,
            ])
            ->assertCreated();

        return MoneyMovement::where('payment_account_id', $account->id)
            ->where('amount_minor', $amountMinor)
            ->firstOrFail();
    }
}
