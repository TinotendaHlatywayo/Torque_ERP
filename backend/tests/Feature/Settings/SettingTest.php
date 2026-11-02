<?php

namespace Tests\Feature\Settings;

use App\Models\Branch;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private User $admin;

    private User $cashier;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');

        $this->admin = $this->user('Admin');
        $this->cashier = $this->user('Cashier');
        $this->platformAdmin = User::factory()->platformAdmin()->create();

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->cashier, 'cashier');
    }

    public function test_branch_setting_can_be_set_and_read(): void
    {
        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/settings", [
                'key' => 'registration_notes',
                'value' => 'Updated notes',
            ])
            ->assertOk()
            ->assertJsonPath('data.key', 'registration_notes')
            ->assertJsonPath('data.value', 'Updated notes');

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/settings")
            ->assertOk()
            ->assertJsonPath('data.registration_notes', 'Updated notes');

        $this->assertDatabaseHas('audit_logs', [
            'branch_id' => $this->branchA->id,
            'action' => 'setting.updated',
        ]);
    }

    public function test_disallowed_setting_key_is_rejected(): void
    {
        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/settings", [
                'key' => 'secret_feature',
                'value' => 'value',
            ])
            ->assertStatus(422);
    }

    public function test_branch_setting_requires_permission(): void
    {
        $this->withAuthToken($this->cashier)
            ->getJson("/api/branches/{$this->branchA->id}/settings")
            ->assertForbidden();

        $this->withAuthToken($this->cashier)
            ->patchJson("/api/branches/{$this->branchA->id}/settings", [
                'key' => 'registration_notes',
                'value' => 'No permission',
            ])
            ->assertForbidden();
    }

    public function test_platform_setting_can_be_managed(): void
    {
        $this->withAuthToken($this->platformAdmin)
            ->patchJson("/api/platforms/{$this->platform->id}/settings", [
                'key' => 'registration_notes',
                'value' => 'Platform notes',
            ])
            ->assertOk();

        $this->withAuthToken($this->platformAdmin)
            ->getJson("/api/platforms/{$this->platform->id}/settings")
            ->assertOk()
            ->assertJsonPath('data.registration_notes', 'Platform notes');
    }
}
