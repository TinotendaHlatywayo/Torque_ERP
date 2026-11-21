<?php

namespace Tests\Feature\Sync;

use App\Models\Branch;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SyncOperation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private User $cashier;

    private Unit $unit;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');

        $this->cashier = $this->user('Cashier');
        $this->membership($this->branchA, $this->cashier, 'cashier');

        $this->unit = $this->unit($this->branchA, 'PCS', true);
        $this->product = $this->product($this->branchA, $this->unit, null, 'SYNC-1', 'Sync Product', null, 1000);
        $this->product->stockLevel()->update(['quantity_on_hand' => '10.0000']);
    }

    public function test_sync_upload_is_idempotent_for_duplicate_operation_id(): void
    {
        $deviceId = 'device-1';
        $operationId = (string) Str::uuid();
        $key = (string) Str::uuid();

        $payload = [
            'device_id' => $deviceId,
            'operations' => [
                [
                    'operation_id' => $operationId,
                    'operation_type' => 'customer_create',
                    'payload_version' => 1,
                    'client_time' => now()->toIso8601String(),
                    'idempotency_key' => $key,
                    'payload' => ['name' => 'Offline Customer'],
                ],
            ],
        ];

        $first = $this->withAuthToken($this->cashier)
            ->postJson("/api/branches/{$this->branchA->id}/sync/upload", $payload)
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'completed')
            ->assertJsonPath('data.results.0.replayed', false);

        $second = $this->withAuthToken($this->cashier)
            ->postJson("/api/branches/{$this->branchA->id}/sync/upload", $payload)
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'completed')
            ->assertJsonPath('data.results.0.replayed', true);

        $this->assertSame(
            $first->json('data.results.0.result'),
            $second->json('data.results.0.result'),
        );

        $this->assertSame(1, SyncOperation::where('branch_id', $this->branchA->id)->count());
    }

    public function test_checkout_operation_creates_sale_once_and_replays_stored_result(): void
    {
        $payload = [
            'device_id' => 'device-1',
            'operations' => [
                [
                    'operation_id' => (string) Str::uuid(),
                    'operation_type' => 'checkout',
                    'payload_version' => 1,
                    'client_time' => now()->toIso8601String(),
                    'idempotency_key' => (string) Str::uuid(),
                    'payload' => [
                        'lines' => [
                            [
                                'product_id' => $this->product->id,
                                'unit_id' => $this->unit->id,
                                'quantity' => '2',
                            ],
                        ],
                        'payments' => [
                            [
                                'method' => 'cash',
                                'amount_minor' => 2000,
                                'currency_code' => 'USD',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $first = $this->withAuthToken($this->cashier)
            ->postJson("/api/branches/{$this->branchA->id}/sync/upload", $payload)
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'completed')
            ->assertJsonPath('data.results.0.replayed', false);

        $saleId = $first->json('data.results.0.result.sale_id');

        $this->assertNotNull($saleId);
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'branch_id' => $this->branchA->id,
            'total_minor' => 2000,
        ]);
        $this->assertSame(1, Sale::where('branch_id', $this->branchA->id)->count());

        $second = $this->withAuthToken($this->cashier)
            ->postJson("/api/branches/{$this->branchA->id}/sync/upload", $payload)
            ->assertOk()
            ->assertJsonPath('data.results.0.status', 'completed')
            ->assertJsonPath('data.results.0.replayed', true);

        $this->assertSame(
            $first->json('data.results.0.result'),
            $second->json('data.results.0.result'),
        );
        $this->assertSame(1, Sale::where('branch_id', $this->branchA->id)->count());
        $this->assertSame(1, SyncOperation::where('branch_id', $this->branchA->id)->count());
        $this->assertSame(1, (int) SyncOperation::first()->attempt_count);
    }

    public function test_sync_requires_authentication_and_membership(): void
    {
        $this->postJson("/api/branches/{$this->branchA->id}/sync/upload", [
            'device_id' => 'device-1',
            'operations' => [],
        ])->assertUnauthorized();

        $otherBranch = $this->branch($this->platform, 'BTB');
        $user = $this->user('Other');
        $this->membership($otherBranch, $user, 'cashier');

        $this->withAuthToken($user)
            ->postJson("/api/branches/{$this->branchA->id}/sync/upload", [
                'device_id' => 'device-1',
                'operations' => [
                    [
                        'operation_id' => (string) Str::uuid(),
                        'operation_type' => 'customer_create',
                        'payload_version' => 1,
                        'client_time' => now()->toIso8601String(),
                        'idempotency_key' => (string) Str::uuid(),
                        'payload' => ['name' => 'Offline Customer'],
                    ],
                ],
            ])
            ->assertForbidden();
    }
}
