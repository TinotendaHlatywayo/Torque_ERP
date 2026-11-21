<?php

namespace Tests\Feature\Sales;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class CheckoutOverridePermissionTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Branch $branch;

    private User $cashier;

    private User $manager;

    private string $productId;

    private string $unitId;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->platform();
        $this->branch = $this->branch($platform, 'COV');
        $this->cashier = $this->user('Override Cashier');
        $this->manager = $this->user('Override Manager');
        $this->membership($this->branch, $this->cashier, Roles::CASHIER);
        $this->membership($this->branch, $this->manager, Roles::MANAGER);

        $unit = $this->unit($this->branch, 'OV');
        $product = $this->product($this->branch, $unit, sku: 'OVERRIDE-1', name: 'Override Product', priceMinor: 1000);
        $product->stockLevel()->update(['quantity_on_hand' => '10.0000']);

        $this->productId = (string) $product->id;
        $this->unitId = (string) $unit->id;
    }

    public function test_cashier_with_reason_cannot_approve_discount_override(): void
    {
        $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->checkoutUrl(), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, AuditLog::where('action', 'price.override.approved')->count());
    }

    public function test_manager_with_reason_can_approve_discount_override(): void
    {
        $this->withAuthToken($this->manager)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson($this->checkoutUrl(), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.total_minor', 850);

        $this->assertSame(1, Sale::count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'price.override.approved',
            'user_id' => $this->manager->id,
        ]);
        $this->assertSame('Manager approved price match', AuditLog::where('action', 'price.override.approved')->firstOrFail()->after['override_reason']);
    }

    private function checkoutUrl(): string
    {
        return "/api/branches/{$this->branch->id}/checkout";
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'lines' => [
                [
                    'product_id' => $this->productId,
                    'unit_id' => $this->unitId,
                    'quantity' => '1',
                    'discount_percent' => 15,
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount_minor' => 850, 'currency_code' => 'USD'],
            ],
            'discount_override_reason' => 'Manager approved price match',
        ];
    }
}
