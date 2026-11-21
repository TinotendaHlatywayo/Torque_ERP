<?php

namespace Tests\Feature\Loyalty;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoyaltyQualification;
use App\Models\LoyaltySaleEvent;
use App\Models\NotificationDelivery;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Branch $branchA;

    private Branch $branchB;

    private User $admin;

    private User $adminB;

    private User $viewOnly;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = $this->platform();
        $this->branchA = $this->branch($platform, 'LOY-A');
        $this->branchB = $this->branch($platform, 'LOY-B');
        $this->admin = $this->user('Loyalty Admin A');
        $this->adminB = $this->user('Loyalty Admin B');
        $this->viewOnly = $this->user('Loyalty View Only');

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchB, $this->adminB, 'admin');
        $this->membership($this->branchA, $this->viewOnly, 'viewer', true, [Permissions::BRANCH_LOYALTY_VIEW]);
    }

    public function test_qualification_uses_committed_sales_and_configurable_inactivity_window(): void
    {
        $this->configure($this->branchA, ['inactivity_days' => 30, 'min_qualifying_minor' => 1000]);
        $customer = $this->customer($this->branchA, 'Qualifying Customer');
        $this->sale($this->branchA, $customer, 1500);

        $result = $this->recalculate($this->branchA);

        $this->assertSame(1, $result['customers_processed']);
        $qualification = $this->qualificationFor($customer);
        $this->assertSame(1, $qualification->qualifying_sale_count);
        $this->assertSame(1500, $qualification->gross_sales_minor);
        $this->assertSame(0, $qualification->returned_minor);
        $this->assertSame(1500, $qualification->net_qualifying_minor);
        $this->assertTrue($qualification->qualifies);
        $this->assertFalse($qualification->is_inactive);

        $this->travel(31)->days();
        $this->recalculate($this->branchA);
        $this->assertTrue($this->qualificationFor($customer)->is_inactive);

        $this->configure($this->branchA, ['inactivity_days' => 365]);
        $this->recalculate($this->branchA);
        $this->assertFalse($this->qualificationFor($customer)->is_inactive);
    }

    public function test_voided_sales_are_excluded_and_documented_returns_reduce_qualification(): void
    {
        $this->configure($this->branchA, ['min_qualifying_minor' => 1000]);
        $customer = $this->customer($this->branchA, 'Return Customer');
        $sale = $this->sale($this->branchA, $customer, 2000);
        $voidedSale = $this->sale($this->branchA, $customer, 5000, 'void-key');
        $voidedSale->update(['voided_at' => now(), 'void_reason' => 'Customer cancelled']);
        $this->saleReturn($this->branchA, $sale, 800);

        $this->recalculate($this->branchA);
        $qualification = $this->qualificationFor($customer);

        $this->assertSame(1, $qualification->qualifying_sale_count);
        $this->assertSame(2000, $qualification->gross_sales_minor);
        $this->assertSame(800, $qualification->returned_minor);
        $this->assertSame(1200, $qualification->net_qualifying_minor);
        $this->assertTrue($qualification->qualifies);

        $this->saleReturn($this->branchA, $sale, 1200);
        $this->recalculate($this->branchA);
        $qualification->refresh();

        $this->assertSame(2000, $qualification->returned_minor);
        $this->assertSame(0, $qualification->net_qualifying_minor);
        $this->assertFalse($qualification->qualifies);
    }

    public function test_duplicate_sale_events_and_recalculation_do_not_double_count(): void
    {
        $this->configure($this->branchA, ['min_qualifying_minor' => 1000]);
        $customer = $this->customer($this->branchA, 'Import Customer');
        $payload = [
            'customer_id' => $customer->id,
            'event_key' => 'import-event-1',
            'source_ref' => 'EXT-SALE-1',
            'total_minor' => 1500,
            'occurred_at' => now()->toIso8601String(),
        ];

        $first = $this->postJson("/api/branches/{$this->branchA->id}/loyalty/sale-events", $payload)
            ->assertCreated()
            ->json('data');
        $replay = $this->postJson("/api/branches/{$this->branchA->id}/loyalty/sale-events", $payload)
            ->assertCreated()
            ->json('data');
        $sameFactsDifferentKey = $this->postJson("/api/branches/{$this->branchA->id}/loyalty/sale-events", array_merge($payload, ['event_key' => 'import-event-1-copy']))
            ->assertCreated()
            ->json('data');

        $this->assertSame($first['id'], $replay['id']);
        $this->assertSame($first['id'], $sameFactsDifferentKey['id']);

        $this->postJson("/api/branches/{$this->branchA->id}/loyalty/sale-events", array_merge($payload, ['total_minor' => 9999]))
            ->assertStatus(409);

        $this->recalculate($this->branchA);
        $this->recalculate($this->branchA);

        $this->assertSame(1, LoyaltySaleEvent::where('branch_id', $this->branchA->id)->count());
        $qualification = $this->qualificationFor($customer);
        $this->assertSame(1, $qualification->qualifying_sale_count);
        $this->assertSame(1500, $qualification->net_qualifying_minor);
    }

    public function test_stacking_respects_floor_price_and_requires_authorized_approval(): void
    {
        $this->configure($this->branchA, [
            'min_qualifying_minor' => 1000,
            'loyalty_discount_percent' => 5,
            'combined_approval_threshold_percent' => 10,
        ]);
        $customer = $this->customer($this->branchA, 'Discount Customer');
        $this->sale($this->branchA, $customer, 2000);
        $this->recalculate($this->branchA);

        $quote = $this->quote($this->admin, $customer, ['manual_discount_percent' => 2])
            ->assertOk()
            ->json('data');
        $this->assertEquals(7.0, $quote['combined_discount_percent']);
        $this->assertSame(1860, $quote['net_minor']);
        $this->assertFalse($quote['floor_price_applied']);
        $this->assertFalse($quote['requires_approval']);

        $this->quote($this->viewOnly, $customer, ['manual_discount_percent' => 8])
            ->assertStatus(422)
            ->assertJsonValidationErrors('approval_reason');

        $approved = $this->quote($this->admin, $customer, [
            'manual_discount_percent' => 8,
            'approval_reason' => 'Manager approved combined promotion',
        ])->assertOk()->json('data');

        $this->assertEquals(13.0, $approved['combined_discount_percent']);
        $this->assertTrue($approved['requires_approval']);
        $this->assertTrue($approved['approved']);
        $this->assertContains('combined_discount_exceeds_threshold', $approved['approval_reasons']);

        $withFloor = $this->quote($this->admin, $customer, [
            'manual_discount_percent' => 8,
            'floor_price_minor' => 1900,
            'approval_reason' => 'Manager approved floor-price override',
        ])->assertOk()->json('data');

        $this->assertSame(1900, $withFloor['net_minor']);
        $this->assertTrue($withFloor['floor_price_applied']);
        $this->assertContains('below_floor_price', $withFloor['approval_reasons']);

        $this->quote($this->admin, $customer, [
            'manual_discount_percent' => 2,
            'floor_price_minor' => 1900,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('approval_reason');
    }

    public function test_winback_requires_consent_and_active_channel_and_export_does_not_send(): void
    {
        $this->configure($this->branchA, ['inactivity_days' => 30, 'min_qualifying_minor' => 1000]);
        $noConsent = $this->customer($this->branchA, 'No Consent');
        $inactiveChannel = $this->customer($this->branchA, 'Inactive Channel');
        $inactiveChannel->update([
            'marketing_consent_at' => now(),
            'marketing_channel' => 'sms',
            'marketing_channel_active' => false,
        ]);
        $eligible = $this->customer($this->branchA, 'Eligible');
        $eligible->update([
            'marketing_consent_at' => now(),
            'marketing_channel' => 'sms',
            'marketing_channel_active' => true,
        ]);

        foreach ([$noConsent, $inactiveChannel, $eligible] as $customer) {
            $this->sale($this->branchA, $customer, 1500);
        }

        $this->travel(31)->days();
        $this->recalculate($this->branchA);

        $candidates = $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/loyalty/winback/candidates")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $candidates);
        $this->assertSame($eligible->id, $candidates[0]['customer_id']);
        $this->assertSame('sms', $candidates[0]['marketing_channel']);

        $export = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/loyalty/winback/export")
            ->assertOk()
            ->json('data');

        $this->assertSame('NOT_RUN', $export['delivery_status']);
        $this->assertSame('not_configured', $export['channel_integration']);
        $this->assertSame('REDACTED_PENDING_CHANNEL_INTEGRATION', $export['contact_disposition']);
        $this->assertCount(1, $export['candidates']);
        $this->assertSame(0, NotificationDelivery::count());
    }

    public function test_loyalty_data_and_permissions_are_branch_scoped(): void
    {
        $this->configure($this->branchA, ['inactivity_days' => 30]);
        $customerA = $this->customer($this->branchA, 'Customer A');
        $this->sale($this->branchA, $customerA, 1500);
        $this->recalculate($this->branchA);
        $customerB = $this->customer($this->branchB, 'Customer B');
        $this->sale($this->branchB, $customerB, 1500);
        $this->recalculate($this->branchB);

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchB->id}/loyalty/config")
            ->assertForbidden();

        $configB = $this->withAuthToken($this->adminB)
            ->getJson("/api/branches/{$this->branchB->id}/loyalty/config")
            ->assertOk()
            ->json('data');
        $this->assertNotSame(30, $configB['inactivity_days']);

        $qualificationsB = $this->withAuthToken($this->adminB)
            ->getJson("/api/branches/{$this->branchB->id}/loyalty/qualifications")
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $qualificationsB);
        $this->assertSame($customerB->id, $qualificationsB[0]['customer_id']);

        $this->assertSame(0, LoyaltyQualification::where('branch_id', $this->branchA->id)
            ->where('customer_id', $customerB->id)
            ->count());

        $this->withAuthToken($this->viewOnly)
            ->putJson("/api/branches/{$this->branchA->id}/loyalty/config", ['inactivity_days' => 7])
            ->assertForbidden();
        $this->withAuthToken($this->viewOnly)
            ->postJson("/api/branches/{$this->branchA->id}/loyalty/recalculate")
            ->assertForbidden();
    }

    public function test_marketing_consent_can_be_recorded_through_customer_api(): void
    {
        $customer = $this->customer($this->branchA, 'Consent Customer');

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/customers/{$customer->id}", [
                'marketing_consent_at' => now()->toIso8601String(),
                'marketing_channel' => 'sms',
                'marketing_channel_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.marketing_channel', 'sms')
            ->assertJsonPath('data.marketing_channel_active', true);

        $customer->refresh();

        $this->assertNotNull($customer->marketing_consent_at);
        $this->assertTrue($customer->hasActiveMarketingChannel());

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/customers/{$customer->id}", [
                'marketing_channel' => 'carrier_pigeon',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('marketing_channel');
    }

    private function configure(Branch $branch, array $overrides): void
    {
        $defaults = [
            'inactivity_days' => 90,
            'min_qualifying_minor' => 1000,
            'min_qualifying_sales' => 1,
            'loyalty_discount_percent' => 5,
            'combined_approval_threshold_percent' => 10,
        ];

        $this->withAuthToken($this->adminFor($branch))
            ->putJson("/api/branches/{$branch->id}/loyalty/config", array_merge($defaults, $overrides))
            ->assertOk();
    }

    private function adminFor(Branch $branch): User
    {
        return $branch->id === $this->branchA->id ? $this->admin : $this->adminB;
    }

    private function customer(Branch $branch, string $name): Customer
    {
        return Customer::create([
            'branch_id' => $branch->id,
            'name' => $name,
            'phone' => '+263700000'.rand(100, 999),
            'is_active' => true,
        ]);
    }

    private function sale(Branch $branch, Customer $customer, int $totalMinor, string $idempotencyKey = ''): Sale
    {
        $key = $idempotencyKey !== '' ? $idempotencyKey : 'loyalty-sale-'.uniqid();

        return Sale::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'sale_number' => 'LOY-SALE-'.uniqid(),
            'status' => 'posted',
            'payment_status' => 'paid',
            'currency_code' => $branch->currency_code,
            'subtotal_minor' => $totalMinor,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => $totalMinor,
            'paid_minor' => $totalMinor,
            'idempotency_key' => $key,
            'request_hash' => hash('sha256', $key),
        ]);
    }

    private function saleReturn(Branch $branch, Sale $sale, int $totalMinor): SaleReturn
    {
        return SaleReturn::create([
            'branch_id' => $branch->id,
            'sale_id' => $sale->id,
            'return_number' => 'LOY-RET-'.uniqid(),
            'status' => 'requested',
            'restock_decision' => 'pending',
            'refund_status' => 'none',
            'reason' => 'Customer return',
            'currency_code' => $branch->currency_code,
            'total_minor' => $totalMinor,
            'idempotency_key' => 'loyalty-return-'.uniqid(),
            'request_hash' => hash('sha256', uniqid()),
        ]);
    }

    private function recalculate(Branch $branch): array
    {
        return $this->withAuthToken($this->adminFor($branch))
            ->postJson("/api/branches/{$branch->id}/loyalty/recalculate")
            ->assertOk()
            ->json('data');
    }

    private function quote(User $actor, Customer $customer, array $overrides): TestResponse
    {
        $payload = array_merge([
            'customer_id' => $customer->id,
            'unit_price_minor' => 2000,
            'quantity' => 1,
            'floor_price_minor' => 0,
            'tax_rate_percent' => 0,
        ], $overrides);

        return $this->withAuthToken($actor)
            ->postJson("/api/branches/{$this->branchA->id}/loyalty/discount/quote", $payload);
    }

    private function qualificationFor(Customer $customer): LoyaltyQualification
    {
        return LoyaltyQualification::where('branch_id', $customer->branch_id)
            ->where('customer_id', $customer->id)
            ->firstOrFail();
    }
}
