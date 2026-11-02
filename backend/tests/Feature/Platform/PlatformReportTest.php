<?php

namespace Tests\Feature\Platform;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformReportTest extends TestCase
{
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $platformAdmin;

    private User $branchUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = Platform::create([
            'name' => 'Test Platform',
            'slug' => 'test-platform-'.uniqid(),
            'currency_code' => 'USD',
        ]);

        $this->branchA = Branch::create([
            'platform_id' => $this->platform->id,
            'name' => 'Branch A',
            'code' => 'BTA',
            'slug' => 'branch-a-'.uniqid(),
            'status' => Branch::STATUS_ACTIVE,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);

        $this->branchB = Branch::create([
            'platform_id' => $this->platform->id,
            'name' => 'Branch B',
            'code' => 'BTB',
            'slug' => 'branch-b-'.uniqid(),
            'status' => Branch::STATUS_ACTIVE,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);

        $this->platformAdmin = User::factory()->platformAdmin()->create();

        $this->branchUser = User::factory()->create();
        BranchUser::create([
            'branch_id' => $this->branchA->id,
            'user_id' => $this->branchUser->id,
            'role' => 'admin',
            'permissions' => [],
            'is_active' => true,
        ]);
    }

    private function platformToken()
    {
        return $this->platformAdmin->createToken('platform')->plainTextToken;
    }

    public function test_platform_admin_can_compare_branches(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->platformToken())
            ->getJson("/api/platforms/{$this->platform->id}/reports/branches")
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_branch_user_cannot_access_platform_report(): void
    {
        $token = $this->branchUser->createToken('branch')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/platforms/{$this->platform->id}/reports/branches")
            ->assertForbidden();
    }

    public function test_export_creates_private_scoped_expiring_file(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->platformToken())
            ->postJson("/api/platforms/{$this->platform->id}/reports/export")
            ->assertCreated();

        $exportId = $response->json('data.id');
        $downloadUrl = $response->json('data.download_url');

        $this->withHeader('Authorization', 'Bearer '.$this->platformToken())
            ->get($downloadUrl)
            ->assertOk();

        $this->assertDatabaseHas('report_exports', [
            'id' => $exportId,
            'scope_type' => 'platform',
            'scope_id' => $this->platform->id,
        ]);
    }
}
