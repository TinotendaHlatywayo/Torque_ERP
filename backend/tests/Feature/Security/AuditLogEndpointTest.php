<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class AuditLogEndpointTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $adminA;

    private User $cashierA;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'ALA');
        $this->branchB = $this->branch($this->platform, 'ALB');
        $this->adminA = $this->user('Audit Admin');
        $this->cashierA = $this->user('Audit Cashier');
        $this->platformAdmin = User::factory()->platformAdmin()->create();

        $this->membership($this->branchA, $this->adminA, 'admin');
        $this->membership($this->branchA, $this->cashierA, 'cashier');

        AuditLog::create([
            'branch_id' => $this->branchA->id,
            'user_id' => $this->adminA->id,
            'action' => 'product.updated',
            'entity_type' => 'product',
            'entity_id' => 'product-1',
            'after' => ['name' => 'Updated'],
        ]);
        AuditLog::create([
            'branch_id' => $this->branchA->id,
            'user_id' => $this->cashierA->id,
            'action' => 'sale.created',
            'entity_type' => 'sale',
            'entity_id' => 'sale-1',
        ]);
        AuditLog::create([
            'branch_id' => $this->branchB->id,
            'user_id' => $this->adminA->id,
            'action' => 'product.updated',
            'entity_type' => 'product',
            'entity_id' => 'product-2',
        ]);
        AuditLog::create([
            'user_id' => $this->platformAdmin->id,
            'action' => 'report.exported',
            'entity_type' => 'report',
            'entity_id' => 'report-1',
        ]);
    }

    public function test_branch_audit_logs_are_scoped_filtered_and_paginated(): void
    {
        $response = $this->withAuthToken($this->adminA)
            ->getJson("/api/branches/{$this->branchA->id}/audit-logs?per_page=1&page=2")
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);

        $this->assertCount(1, $response->json('data'));
        $this->assertNotNull($response->json('data.0.created_at'));
        $this->assertSame($this->branchA->id, $response->json('data.0.branch_id'));

        $this->withAuthToken($this->adminA)
            ->getJson("/api/branches/{$this->branchA->id}/audit-logs?event=product.updated&actor_id={$this->adminA->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.event', 'product.updated')
            ->assertJsonPath('data.0.actor.name', 'Audit Admin')
            ->assertJsonPath('data.0.subject_type', 'product')
            ->assertJsonPath('data.0.subject_id', 'product-1')
            ->assertJsonPath('data.0.detail.after.name', 'Updated');
    }

    public function test_branch_user_without_audit_permission_cannot_read_audit_logs(): void
    {
        $this->withAuthToken($this->cashierA)
            ->getJson("/api/branches/{$this->branchA->id}/audit-logs")
            ->assertForbidden();
    }

    public function test_platform_audit_logs_are_scoped_and_permission_guarded(): void
    {
        $this->withAuthToken($this->adminA)
            ->getJson('/api/platform/audit-logs')
            ->assertForbidden();

        $this->withAuthToken($this->platformAdmin)
            ->getJson('/api/platform/audit-logs')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.event', 'report.exported')
            ->assertJsonPath('data.0.actor.name', $this->platformAdmin->name)
            ->assertJsonPath('data.0.branch_id', null);
    }
}
