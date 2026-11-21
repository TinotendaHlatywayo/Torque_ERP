<?php

namespace Tests\Feature\Sync;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Platform;
use App\Models\SyncOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class SyncRecoveryTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');

        $this->cashier = $this->user('Cashier');
        $this->membership($this->branchA, $this->cashier, 'cashier');
    }

    public function test_duplicate_retry_returns_prior_result_without_repeating(): void
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
            ->assertOk();

        // Simulate at-least-once retry.
        $second = $this->withAuthToken($this->cashier)
            ->postJson("/api/branches/{$this->branchA->id}/sync/upload", $payload)
            ->assertOk();

        $this->assertSame(
            $first->json('data.results.0.result'),
            $second->json('data.results.0.result'),
        );

        $this->assertSame(1, SyncOperation::where('branch_id', $this->branchA->id)->count());
        $this->assertSame(1, Customer::where('branch_id', $this->branchA->id)->count());
    }

    public function test_malformed_payload_fails_per_operation_without_aborting_batch(): void
    {
        $operations = [
            [
                'operation_id' => (string) Str::uuid(),
                'operation_type' => 'customer_create',
                'payload_version' => 1,
                'client_time' => now()->toIso8601String(),
                'idempotency_key' => (string) Str::uuid(),
                'payload' => [],
            ],
            [
                'operation_id' => (string) Str::uuid(),
                'operation_type' => 'customer_create',
                'payload_version' => 1,
                'client_time' => now()->toIso8601String(),
                'idempotency_key' => (string) Str::uuid(),
                'payload' => ['name' => 'Valid Customer'],
            ],
        ];

        $this->withAuthToken($this->cashier)
            ->postJson("/api/branches/{$this->branchA->id}/sync/upload", [
                'device_id' => 'device-1',
                'operations' => $operations,
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 2)
            ->assertJsonPath('data.results.0.status', 'failed')
            ->assertJsonPath('data.results.0.error_class', 'ValidationException')
            ->assertJsonPath('data.results.1.status', 'completed')
            ->assertJsonPath('data.results.1.replayed', false);

        $this->assertSame(2, SyncOperation::where('branch_id', $this->branchA->id)->count());
        $this->assertSame(1, Customer::where('branch_id', $this->branchA->id)->count());
        $statuses = SyncOperation::where('branch_id', $this->branchA->id)
            ->get()
            ->mapWithKeys(fn (SyncOperation $operation): array => [$operation->operation_id => $operation->status])
            ->all();

        $this->assertSame('failed', $statuses[$operations[0]['operation_id']]);
        $this->assertSame('completed', $statuses[$operations[1]['operation_id']]);
    }

    public function test_expired_session_is_rejected(): void
    {
        $this->postJson("/api/branches/{$this->branchA->id}/sync/upload", [
            'device_id' => 'device-1',
            'operations' => [],
        ])->assertUnauthorized();
    }
}
