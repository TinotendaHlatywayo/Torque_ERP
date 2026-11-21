<?php

namespace Tests\Feature\Platform;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Platform $platform;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = Platform::create([
            'name' => 'Test Platform',
            'slug' => 'test-platform-'.uniqid(),
            'currency_code' => 'USD',
        ]);

        $this->platformAdmin = User::factory()->platformAdmin()->create();
    }

    private function withPlatformAdmin()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->platformAdmin->createToken('platform')->plainTextToken);
    }

    public function test_branch_index_returns_platform_and_staff_details(): void
    {
        $approver = User::factory()->create(['name' => 'Approving Admin']);
        $suspender = User::factory()->create(['name' => 'Suspending Admin']);

        Branch::create([
            'platform_id' => $this->platform->id,
            'name' => 'Populated Branch',
            'code' => 'POP',
            'slug' => 'populated-branch',
            'status' => Branch::STATUS_SUSPENDED,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
            'approved_at' => now(),
            'approved_by' => $approver->id,
            'suspended_at' => now(),
            'suspended_by' => $suspender->id,
            'suspension_reason' => 'Compliance review',
            'settings' => ['registration_notes' => 'Opening branch'],
        ]);

        $this->withPlatformAdmin()
            ->getJson('/api/platform/branches')
            ->assertOk()
            ->assertJsonPath('data.0.platform', 'Test Platform')
            ->assertJsonPath('data.0.approved_by', 'Approving Admin')
            ->assertJsonPath('data.0.suspended_by', 'Suspending Admin')
            ->assertJsonPath('data.0.registration_notes', 'Opening branch');
    }

    public function test_branch_registration_approval_suspension_activation_and_archive(): void
    {
        $response = $this->withPlatformAdmin()
            ->postJson('/api/platform/branches', [
                'platform_id' => $this->platform->id,
                'name' => 'New Branch',
                'code' => 'NEW',
                'slug' => 'new-branch',
                'timezone' => 'Africa/Harare',
                'currency_code' => 'USD',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $branchId = $response->json('data.id');

        $this->withPlatformAdmin()
            ->patchJson("/api/platform/branches/{$branchId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->withPlatformAdmin()
            ->patchJson("/api/platform/branches/{$branchId}/suspend", [
                'reason' => 'Maintenance',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended')
            ->assertJsonPath('data.suspension_reason', 'Maintenance');

        $this->withPlatformAdmin()
            ->patchJson("/api/platform/branches/{$branchId}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->withPlatformAdmin()
            ->patchJson("/api/platform/branches/{$branchId}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertSoftDeleted('branches', ['id' => $branchId]);
    }

    public function test_suspended_branch_rejects_writes_but_allows_reads(): void
    {
        $branch = Branch::create([
            'platform_id' => $this->platform->id,
            'name' => 'Suspended Branch',
            'code' => 'SUS',
            'slug' => 'suspended-branch',
            'status' => Branch::STATUS_SUSPENDED,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);

        $user = User::factory()->create();
        BranchUser::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'permissions' => [],
            'is_active' => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/branches/{$branch->id}/products")
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/branches/{$branch->id}/products", [
                'name' => 'Blocked',
                'sku' => 'BLOCKED-1',
                'base_unit_id' => '01',
                'selling_price' => '1.00',
            ])
            ->assertForbidden();
    }

    public function test_duplicate_slug_and_host_are_rejected(): void
    {
        Branch::create([
            'platform_id' => $this->platform->id,
            'name' => 'Existing',
            'code' => 'EXIST',
            'slug' => 'existing-branch',
            'host' => 'existing.example.com',
            'status' => Branch::STATUS_ACTIVE,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);

        $this->withPlatformAdmin()
            ->postJson('/api/platform/branches', [
                'platform_id' => $this->platform->id,
                'name' => 'Duplicate Slug',
                'code' => 'DUP',
                'slug' => 'existing-branch',
                'timezone' => 'Africa/Harare',
                'currency_code' => 'USD',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->withPlatformAdmin()
            ->postJson('/api/platform/branches', [
                'platform_id' => $this->platform->id,
                'name' => 'Duplicate Host',
                'code' => 'DUP2',
                'slug' => 'duplicate-host',
                'host' => 'existing.example.com',
                'timezone' => 'Africa/Harare',
                'currency_code' => 'USD',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('host');
    }
}
