<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class PublicStorefrontTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Branch $branch;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = Platform::create(['name' => 'TMS', 'slug' => 'tms-'.uniqid(), 'currency_code' => 'USD']);
        $this->branch = $this->branch($platform, 'PUB');
        $this->branch->update([
            'slug' => 'public-'.uniqid(),
            'name' => 'Public Branch',
            'status' => Branch::STATUS_ACTIVE,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);
        config(['catalog.public_branch_slug' => $this->branch->slug]);

        $unit = Unit::create([
            'branch_id' => $this->branch->id,
            'code' => 'EA-'.strtoupper(bin2hex(random_bytes(2))),
            'name' => 'Each',
            'allow_decimal' => false,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'base_unit_id' => $unit->id,
            'sku' => 'FILTER-1',
            'name' => 'Air Filter',
            'selling_price_minor' => 1250,
            'currency_code' => 'USD',
            'track_stock' => true,
            'is_active' => true,
            'public_slug' => 'air-filter',
            'public_description' => 'Customer facing description.',
            'is_public' => true,
        ]);

        StockLevel::create([
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => '5',
            'quantity_reserved' => '0',
            'reorder_level' => '0',
        ]);
    }

    public function test_customer_can_register_and_view_account(): void
    {
        $this->withHeaders([
            'Origin' => 'http://127.0.0.1:5173',
            'Referer' => 'http://127.0.0.1:5173/',
        ])->postJson('/api/public/register', [
            'name' => 'Example Customer',
            'email' => 'buyer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()
            ->assertJsonPath('user.email', 'buyer@example.com')
            ->assertJsonCount(0, 'orders')
            ->assertJsonCount(0, 'reservations');

        $this->assertDatabaseHas('users', ['email' => 'buyer@example.com']);

        $this->withHeaders([
            'Origin' => 'http://127.0.0.1:5173',
            'Referer' => 'http://127.0.0.1:5173/',
        ])->getJson('/api/public/account')->assertOk()
            ->assertJsonPath('user.email', 'buyer@example.com');
    }

    public function test_customer_can_place_order_and_stock_is_reduced(): void
    {
        $customer = User::factory()->create(['email' => 'buyer@example.com', 'name' => 'Example Customer']);

        $response = $this->withAuthToken($customer)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/public/checkout', [
                'lines' => [
                    ['product_slug' => 'air-filter', 'quantity' => '2'],
                ],
                'phone' => '+263 77 123 4567',
                'address' => 'Rusape',
            ])->assertCreated()
            ->assertJsonPath('order.status', 'posted')
            ->assertJsonPath('order.payment_status', 'unpaid')
            ->assertJsonPath('order.total_minor', 2500);

        $sale = Sale::firstOrFail();
        $this->assertSame($sale->id, $response->json('order.id'));
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'branch_id' => $this->branch->id,
            'actor_id' => $customer->id,
            'status' => 'posted',
            'total_minor' => 2500,
        ]);
        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'quantity_on_hand' => '3.0000',
        ]);

        $this->getJson('/api/public/account')->assertOk()
            ->assertJsonPath('orders.0.sale_number', $sale->sale_number);
    }

    public function test_checkout_requires_authentication_and_available_stock(): void
    {
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/public/checkout', [
                'lines' => [['product_slug' => 'air-filter', 'quantity' => '1']],
            ])->assertUnauthorized();

        $customer = User::factory()->create(['email' => 'buyer@example.com']);
        $this->withAuthToken($customer)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/public/checkout', [
                'lines' => [['product_slug' => 'air-filter', 'quantity' => '9']],
            ])->assertUnprocessable();

        $this->assertSame(0, Sale::count());
    }

    public function test_logged_in_customer_reservation_is_linked_to_account(): void
    {
        $customer = User::factory()->create(['email' => 'reserve@example.com']);

        $this->withAuthToken($customer)
            ->postJson('/api/public/reservations', [
                'product_slug' => 'air-filter',
                'idempotency_key' => (string) Str::uuid(),
                'name' => 'Example Customer',
                'email' => 'reserve@example.com',
                'quantity' => '1',
            ])->assertSuccessful();

        $this->assertDatabaseHas('reservations', [
            'branch_id' => $this->branch->id,
            'actor_id' => $customer->id,
        ]);
        $this->assertSame(1, Reservation::count());
    }
}
