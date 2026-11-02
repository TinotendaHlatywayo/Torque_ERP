<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class BranchContextTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->branchB = $this->branch($this->platform, 'BTB');

        $this->user = $this->user('Multi Branch User');

        $this->membership($this->branchA, $this->user, 'cashier');
        $this->membership($this->branchB, $this->user, 'viewer');
    }

    public function test_explicit_branch_membership_is_verified_on_every_request(): void
    {
        $this->withAuthToken($this->user)
            ->getJson("/api/branches/{$this->branchA->id}/products")
            ->assertOk();

        $this->withAuthToken($this->user)
            ->getJson("/api/branches/{$this->branchB->id}/products")
            ->assertOk();
    }

    public function test_direct_api_access_to_unauthorized_branch_is_rejected(): void
    {
        $otherBranch = $this->branch($this->platform, 'BTC');

        $this->withAuthToken($this->user)
            ->getJson("/api/branches/{$otherBranch->id}/products")
            ->assertForbidden();
    }

    public function test_stale_membership_is_rejected(): void
    {
        $membership = BranchUser::where('branch_id', $this->branchA->id)
            ->where('user_id', $this->user->id)
            ->firstOrFail();

        $membership->update(['is_active' => false]);

        $this->withAuthToken($this->user)
            ->getJson("/api/branches/{$this->branchA->id}/products")
            ->assertForbidden();
    }

    public function test_suspended_branch_rejects_writes(): void
    {
        $this->branchA->update(['status' => Branch::STATUS_SUSPENDED]);

        $this->withAuthToken($this->user)
            ->getJson("/api/branches/{$this->branchA->id}/products")
            ->assertOk();

        $this->withAuthToken($this->user)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Blocked',
                'sku' => 'BLOCKED-1',
                'base_unit_id' => '01',
                'selling_price' => '1.00',
            ])
            ->assertForbidden();
    }

    public function test_unauthorized_host_is_rejected(): void
    {
        $this->branchA->update(['host' => 'allowed.example.com']);

        $this->withAuthToken($this->user)
            ->getJson("/api/branches/{$this->branchA->id}/products")
            ->assertForbidden();
    }
}
