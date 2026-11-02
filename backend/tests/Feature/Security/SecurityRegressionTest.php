<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Platform;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $adminA;

    private User $adminB;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->branchB = $this->branch($this->platform, 'BTB');

        $this->adminA = $this->user('Admin A');
        $this->adminB = $this->user('Admin B');
        $this->platformAdmin = User::factory()->platformAdmin()->create();

        $this->membership($this->branchA, $this->adminA, 'admin');
        $this->membership($this->branchB, $this->adminB, 'admin');
    }

    public function test_platform_admin_without_membership_cannot_read_branch_data(): void
    {
        $this->withAuthToken($this->platformAdmin)
            ->getJson("/api/branches/{$this->branchA->id}/products")
            ->assertForbidden();
    }

    public function test_branch_user_cannot_access_another_branch(): void
    {
        $this->withAuthToken($this->adminA)
            ->getJson("/api/branches/{$this->branchB->id}/products")
            ->assertForbidden();
    }

    public function test_suspended_branch_rejects_writes(): void
    {
        $this->branchA->update(['status' => Branch::STATUS_SUSPENDED]);

        $this->withAuthToken($this->adminA)
            ->getJson("/api/branches/{$this->branchA->id}/products")
            ->assertOk();

        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Blocked',
                'sku' => 'BLOCKED-1',
                'base_unit_id' => '01',
                'selling_price' => '1.00',
            ])
            ->assertForbidden();
    }

    public function test_audit_logs_are_immutable(): void
    {
        $log = AuditLog::create([
            'branch_id' => $this->branchA->id,
            'user_id' => $this->adminA->id,
            'action' => 'test.action',
            'entity_type' => 'test',
            'entity_id' => '1',
        ]);

        try {
            $log->update(['action' => 'changed']);
            $this->fail('Expected audit log update to be blocked.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit logs are immutable.', $exception->getMessage());
        }

        try {
            $log->delete();
            $this->fail('Expected audit log deletion to be blocked.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit logs are immutable.', $exception->getMessage());
        }
    }

    public function test_sensitive_values_are_redacted_in_audit_logs(): void
    {
        $logger = app(AuditLogger::class);

        $logger->log(
            'test.sensitive',
            'test',
            ['password' => 'secret', 'token' => 'abc'],
            ['card_number' => '4111', 'safe' => 'value'],
            $this->branchA->id,
            $this->adminA,
        );

        $log = AuditLog::where('action', 'test.sensitive')->firstOrFail();

        $this->assertSame('[redacted]', $log->before['password']);
        $this->assertSame('[redacted]', $log->before['token']);
        $this->assertSame('[redacted]', $log->after['card_number']);
        $this->assertSame('value', $log->after['safe']);
    }
}
