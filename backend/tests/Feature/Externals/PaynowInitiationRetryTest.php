<?php

namespace Tests\Feature\Externals;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\StockLevel;
use App\Models\Unit;
use App\Support\PaynowGateway;
use App\Support\PaynowHash;
use App\Support\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaynowInitiationRetryTest extends TestCase
{
    use RefreshDatabase;

    private const INITIATE_URL = 'https://www.paynow.co.zw/interface/initiatetransaction';

    private const REDIRECT_URL = 'https://www.paynow.co.zw/Payment/ConfirmPayment/retry';

    private Branch $branch;

    private Product $product;

    private string $key = 'initiation-retry-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://store.example.test',
            'paynow.enabled' => true,
            'paynow.integration_id' => '12345',
            'paynow.integration_key' => $this->key,
            'paynow.currency' => 'USD',
        ]);

        $platform = Platform::create(['name' => 'TMS', 'slug' => 'tms-'.uniqid(), 'currency_code' => 'USD']);
        $this->branch = Branch::create([
            'platform_id' => $platform->id,
            'name' => 'Retry Branch',
            'code' => 'PR-'.strtoupper(bin2hex(random_bytes(2))),
            'slug' => 'retry-'.uniqid(),
            'status' => Branch::STATUS_ACTIVE,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);
        $unit = Unit::create([
            'branch_id' => $this->branch->id,
            'code' => 'EA-'.strtoupper(bin2hex(random_bytes(2))),
            'name' => 'Each',
            'allow_decimal' => false,
            'is_active' => true,
        ]);
        $category = Category::create(['branch_id' => $this->branch->id, 'name' => 'Retry', 'slug' => 'retry', 'is_active' => true]);
        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'sku' => 'RETRY-1',
            'name' => 'Retry Product',
            'selling_price_minor' => 2500,
            'currency_code' => 'USD',
            'track_stock' => true,
            'is_active' => true,
            'public_slug' => 'retry-product',
            'public_description' => 'Retry product.',
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

    public function test_failed_initiation_can_be_retried(): void
    {
        $created = $this->createReservation();
        $attempts = 0;

        Http::fake([
            self::INITIATE_URL => function () use (&$attempts) {
                $attempts++;

                return $attempts === 1
                    ? Http::response('status=Message&hash=invalid', 200)
                    : Http::response($this->successfulInitiationBody(), 200);
            },
        ]);
        Http::preventStrayRequests();

        $this->assertNull(app(PaynowGateway::class)->start($created['reservation'], $created['token']));
        $this->assertSame('failed', ReservationPayment::firstOrFail()->status);

        $redirect = app(PaynowGateway::class)->start($created['reservation'], $created['token']);

        $this->assertSame(self::REDIRECT_URL, $redirect);
        $this->assertSame('pending', ReservationPayment::firstOrFail()->status);
        Http::assertSentCount(2);
    }

    public function test_unknown_initiation_can_be_retried(): void
    {
        $created = $this->createReservation();

        Http::fake([
            self::INITIATE_URL => Http::response($this->successfulInitiationBody(), 200),
        ]);
        Http::preventStrayRequests();

        $this->assertSame(self::REDIRECT_URL, app(PaynowGateway::class)->start($created['reservation'], $created['token']));

        ReservationPayment::firstOrFail()->update([
            'status' => 'initiation_unknown',
            'redirect_url' => null,
            'poll_url' => null,
        ]);

        $redirect = app(PaynowGateway::class)->start($created['reservation'], $created['token']);

        $this->assertSame(self::REDIRECT_URL, $redirect);
        $this->assertSame('pending', ReservationPayment::firstOrFail()->status);
        Http::assertSentCount(2);
    }

    private function successfulInitiationBody(): string
    {
        $values = [
            'status' => 'Ok',
            'browserurl' => self::REDIRECT_URL,
            'pollurl' => 'https://www.paynow.co.zw/Interface/CheckPayment/?guid=retry',
        ];

        return http_build_query([
            ...$values,
            'hash' => PaynowHash::make($values, $this->key),
        ]);
    }

    /** @return array{reservation: Reservation, token: string} */
    private function createReservation(): array
    {
        return app(ReservationService::class)->create($this->branch, $this->product, [
            'quantity' => '2',
            'name' => 'Retry Customer',
            'phone' => '+263771234567',
        ], (string) Str::uuid());
    }
}
