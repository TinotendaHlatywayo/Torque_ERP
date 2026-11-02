<?php

namespace Tests\Feature\Finance;

use App\Models\Branch;
use App\Models\FxRate;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class FxConversionTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->admin = $this->user('Admin');
        $this->membership($this->branchA, $this->admin, 'admin');
    }

    public function test_fx_rate_history_is_preserved_and_used_by_effective_time(): void
    {
        FxRate::create([
            'from_currency' => 'USD',
            'to_currency' => 'ZAR',
            'rate' => '18.00000000',
            'source' => 'manual',
            'effective_at' => '2026-01-01 00:00:00',
            'entered_by' => $this->admin->id,
        ]);

        FxRate::create([
            'from_currency' => 'USD',
            'to_currency' => 'ZAR',
            'rate' => '20.00000000',
            'source' => 'manual',
            'effective_at' => '2026-02-01 00:00:00',
            'entered_by' => $this->admin->id,
        ]);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/finance/fx/convert", [
                'amount_minor' => 100,
                'from_currency' => 'USD',
                'to_currency' => 'ZAR',
                'at' => '2026-01-15',
            ])
            ->assertOk()
            ->assertJsonPath('data.amount_minor', 1800)
            ->assertJsonPath('data.rate', '18.00000000');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/finance/fx/convert", [
                'amount_minor' => 100,
                'from_currency' => 'USD',
                'to_currency' => 'ZAR',
                'at' => '2026-02-15',
            ])
            ->assertOk()
            ->assertJsonPath('data.amount_minor', 2000)
            ->assertJsonPath('data.rate', '20.00000000');

        $this->assertSame(2, FxRate::count());
    }

    public function test_same_currency_conversion_does_not_require_a_rate(): void
    {
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/finance/fx/convert", [
                'amount_minor' => 1234,
                'from_currency' => 'USD',
                'to_currency' => 'USD',
            ])
            ->assertOk()
            ->assertJsonPath('data.amount_minor', 1234)
            ->assertJsonPath('data.rate', '1.00000000');
    }
}
