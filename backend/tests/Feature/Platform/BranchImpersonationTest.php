<?php

namespace Tests\Feature\Platform;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branch;

    private User $platformAdmin;

    private User $branchAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders(['Origin' => 'http://127.0.0.1:5173']);

        $this->platform = Platform::create([
            'name' => 'Test Platform',
            'slug' => 'test-platform-'.uniqid(),
            'currency_code' => 'USD',
        ]);

        $this->branch = Branch::create([
            'platform_id' => $this->platform->id,
            'name' => 'Test Branch',
            'code' => 'TST-01',
            'slug' => 'test-branch-'.uniqid(),
            'status' => Branch::STATUS_ACTIVE,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
            'approved_at' => now(),
        ]);

        $this->platformAdmin = User::factory()->platformAdmin()->create([
            'email' => 'platform-admin@example.test',
        ]);

        $this->branchAdmin = User::factory()->create([
            'email' => 'branch-admin@example.test',
        ]);

        BranchUser::create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->branchAdmin->id,
            'role' => 'admin',
            'permissions' => [],
            'is_active' => true,
        ]);
    }

    public function test_platform_admin_can_open_branch_and_return_without_signing_in_again(): void
    {
        $this->postJson('/login', ['email' => $this->platformAdmin->email, 'password' => 'password'])
            ->assertNoContent();

        $this->postJson("/api/platform/branches/{$this->branch->id}/impersonate")
            ->assertOk()
            ->assertJsonPath('data.branch_id', $this->branch->id);

        $this->app['auth']->forgetGuards();

        $me = $this->getJson('/api/user')->assertOk()->json();

        $this->assertFalse($me['user']['is_platform_admin']);
        $this->assertSame('branch_impersonation', $me['account_type']);
        $this->assertSame('/app', $me['default_destination']);
        $this->assertTrue($me['impersonation']['active']);
        $this->assertSame($this->branch->id, $me['impersonation']['branch_id']);
        $this->assertSame($this->branchAdmin->id, $me['user']['id']);

        $this->getJson("/api/branches/{$this->branch->id}/products")->assertOk();
        $this->getJson('/api/platform/access')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform.branch.impersonated',
            'branch_id' => $this->branch->id,
            'user_id' => $this->platformAdmin->id,
        ]);

        $this->postJson('/api/platform/branch-session/return')
            ->assertOk()
            ->assertJsonPath('data.user.id', $this->platformAdmin->id);

        $this->app['auth']->forgetGuards();

        $restored = $this->getJson('/api/user')->assertOk()->json();

        $this->assertTrue($restored['user']['is_platform_admin']);
        $this->assertSame('platform', $restored['account_type']);
        $this->assertFalse($restored['impersonation']['active']);
        $this->getJson('/api/platform/access')->assertOk();
    }

    public function test_branch_user_cannot_impersonate_or_return_from_a_session_they_do_not_own(): void
    {
        $this->postJson('/login', ['email' => $this->branchAdmin->email, 'password' => 'password'])
            ->assertNoContent();

        $this->postJson("/api/platform/branches/{$this->branch->id}/impersonate")
            ->assertForbidden();

        $this->postJson('/api/platform/branch-session/return')
            ->assertForbidden();
    }

    public function test_suspended_branch_cannot_be_opened_from_the_platform(): void
    {
        $this->branch->update(['status' => Branch::STATUS_SUSPENDED]);

        $this->postJson('/login', ['email' => $this->platformAdmin->email, 'password' => 'password'])
            ->assertNoContent();

        $this->postJson("/api/platform/branches/{$this->branch->id}/impersonate")
            ->assertUnprocessable();
    }
}
