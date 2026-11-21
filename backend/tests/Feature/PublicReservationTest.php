<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Category;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\StockLevel;
use App\Models\Unit;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\PaynowGateway;
use App\Support\PaynowHash;
use App\Support\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class PublicReservationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $platform = Platform::create(['name' => 'TMS', 'slug' => 'tms-'.uniqid(), 'currency_code' => 'USD']);
        $this->branch = Branch::create([
            'platform_id' => $platform->id,
            'name' => 'Rusape Branch',
            'code' => 'TMS-'.strtoupper(bin2hex(random_bytes(2))),
            'slug' => 'rusape-'.uniqid(),
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
        $category = Category::create(['branch_id' => $this->branch->id, 'name' => 'Filters', 'slug' => 'filters', 'is_active' => true]);
        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'sku' => 'FILTER-1',
            'name' => 'Air Filter',
            'description' => 'Private description',
            'selling_price_minor' => 1250,
            'currency_code' => 'USD',
            'track_stock' => true,
            'is_active' => true,
            'public_slug' => 'air-filter',
            'public_description' => 'Customer facing description.',
            'is_public' => true,
        ]);
        StockLevel::create(['branch_id' => $this->branch->id, 'product_id' => $this->product->id, 'quantity_on_hand' => '5', 'quantity_reserved' => '0', 'reorder_level' => '0']);
    }

    public function test_customer_can_create_a_private_short_stock_hold(): void
    {
        $key = (string) Str::uuid();
        $response = $this->post('/reservations', [
            'product_slug' => 'air-filter', 'idempotency_key' => $key,
            'name' => 'Example Customer', 'phone' => '+263 77 123 4567', 'quantity' => '2',
        ]);

        $reservation = Reservation::firstOrFail();
        $response->assertRedirect(route('reservation.status', hash_hmac('sha256', $this->branch->id.'|'.$key, (string) config('app.key'))));
        $this->assertSame('awaiting_payment', $reservation->status);
        $this->assertSame('2500', (string) $reservation->amount_minor);
        $this->assertSame('2.0000', (string) StockLevel::firstOrFail()->quantity_reserved);
        $this->assertNotSame('Example Customer', $reservation->getRawOriginal('customer_name'));
        $this->get($response->headers->get('Location'))
            ->assertOk()->assertSee('noindex, nofollow', false)->assertSee('Air Filter')->assertDontSee('Example Customer');
    }

    public function test_same_request_replays_once_and_changed_payload_is_rejected(): void
    {
        $key = (string) Str::uuid();
        $payload = ['quantity' => '2', 'name' => 'Example Customer', 'phone' => '+263771234567'];
        $first = app(ReservationService::class)->create($this->branch, $this->product, $payload, $key);
        $replay = app(ReservationService::class)->create($this->branch, $this->product, $payload, $key);

        $this->assertFalse($first['replayed']);
        $this->assertTrue($replay['replayed']);
        $this->assertSame($first['reservation']->id, $replay['reservation']->id);
        $this->assertSame('2.0000', (string) StockLevel::firstOrFail()->quantity_reserved);
        $this->expectException(ConflictHttpException::class);
        app(ReservationService::class)->create($this->branch, $this->product, [...$payload, 'quantity' => '1'], $key);
    }

    public function test_cancellation_and_expiration_release_each_hold_only_once(): void
    {
        $service = app(ReservationService::class);
        $cancel = $service->create($this->branch, $this->product, ['quantity' => '2', 'name' => 'Cancel', 'phone' => '123'], (string) Str::uuid());
        $service->cancel($cancel['token']);
        $service->cancel($cancel['token']);
        $this->assertSame('0.0000', (string) StockLevel::firstOrFail()->quantity_reserved);

        $expire = $service->create($this->branch, $this->product, ['quantity' => '1', 'name' => 'Expire', 'phone' => '456'], (string) Str::uuid());
        $expire['reservation']->update(['expires_at' => now()->subSecond()]);
        $this->assertSame(1, $service->expireDue());
        $this->assertSame(0, $service->expireDue());
        $this->assertSame('0.0000', (string) StockLevel::firstOrFail()->quantity_reserved);
        $this->assertSame('expired', $expire['reservation']->fresh()->status);
    }

    public function test_insufficient_and_fractional_whole_unit_requests_do_not_hold_stock(): void
    {
        $this->post('/reservations', ['product_slug' => 'air-filter', 'idempotency_key' => (string) Str::uuid(), 'name' => 'Customer', 'phone' => '123', 'quantity' => '6'])
            ->assertSessionHasErrors('quantity');
        $this->post('/reservations', ['product_slug' => 'air-filter', 'idempotency_key' => (string) Str::uuid(), 'name' => 'Customer', 'phone' => '123', 'quantity' => '1.5'])
            ->assertSessionHasErrors('quantity');
        $this->assertSame(0, Reservation::count());
        $this->assertSame('0.0000', (string) StockLevel::firstOrFail()->quantity_reserved);
    }

    public function test_expired_hold_is_released_before_new_request_checks_availability(): void
    {
        $service = app(ReservationService::class);
        $first = $service->create($this->branch, $this->product, ['quantity' => '5', 'name' => 'First', 'phone' => '123'], (string) Str::uuid());
        $first['reservation']->update(['expires_at' => now()->subSecond()]);
        $next = $service->create($this->branch, $this->product, ['quantity' => '5', 'name' => 'Next', 'phone' => '456'], (string) Str::uuid());

        $this->assertSame('expired', $first['reservation']->fresh()->status);
        $this->assertSame('awaiting_payment', $next['reservation']->status);
        $this->assertSame('5.0000', (string) StockLevel::firstOrFail()->quantity_reserved);
    }

    public function test_branch_staff_can_view_customer_details_only_with_reservation_permission(): void
    {
        app(ReservationService::class)->create($this->branch, $this->product, ['quantity' => '1', 'name' => 'Private Customer', 'phone' => '+263771234567'], (string) Str::uuid());
        $staff = User::factory()->create();
        BranchUser::create(['branch_id' => $this->branch->id, 'user_id' => $staff->id, 'role' => 'cashier', 'permissions' => [], 'is_active' => true]);
        $token = $staff->createToken('reservation-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/branches/{$this->branch->id}/reservations")
            ->assertOk()->assertJsonPath('data.0.customer_name', 'Private Customer')->assertJsonPath('data.0.customer_phone', '+263771234567');

        $platform = Platform::create(['name' => 'Other', 'slug' => 'other-'.uniqid(), 'currency_code' => 'USD']);
        $other = Branch::create([
            'platform_id' => $platform->id, 'name' => 'Other', 'code' => 'OTH-'.strtoupper(bin2hex(random_bytes(2))),
            'slug' => 'other-'.uniqid(), 'status' => Branch::STATUS_ACTIVE, 'timezone' => 'Africa/Harare', 'currency_code' => 'USD',
        ]);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/branches/{$other->id}/reservations")
            ->assertForbidden();
    }

    public function test_paynow_checkout_verifies_gateway_response_and_confirms_from_signed_polled_status(): void
    {
        $key = 'integration-secret-for-tests';
        config([
            'app.url' => 'https://store.example.test',
            'paynow.enabled' => true,
            'paynow.integration_id' => '12345',
            'paynow.integration_key' => $key,
            'paynow.currency' => 'USD',
        ]);
        $token = (string) Str::uuid();
        $result = app(ReservationService::class)->create($this->branch, $this->product, [
            'quantity' => '2', 'name' => 'Paynow Customer', 'phone' => '+263771234567',
        ], $token);
        $reservation = $result['reservation'];
        $pollUrl = 'https://www.paynow.co.zw/Interface/CheckPayment/?guid=test-guid';
        $initReply = $this->signed(['status' => 'Ok', 'browserurl' => 'https://www.paynow.co.zw/Payment/ConfirmPayment/123', 'pollurl' => $pollUrl, 'paynowreference' => 'PN-123'], $key);
        $paidReply = $this->signed([
            'reference' => $reservation->id, 'paynowreference' => 'PN-123', 'amount' => '25.00',
            'status' => 'Awaiting Delivery', 'pollurl' => $pollUrl,
        ], $key);
        Http::fake([
            'https://www.paynow.co.zw/interface/initiatetransaction' => Http::response(http_build_query($initReply), 200),
            'https://www.paynow.co.zw/Interface/CheckPayment*' => Http::response(http_build_query($paidReply), 200),
        ]);

        $redirect = app(PaynowGateway::class)->start($reservation, $result['token']);
        $this->assertSame('https://www.paynow.co.zw/Payment/ConfirmPayment/123', $redirect);
        $payment = ReservationPayment::firstOrFail();
        $this->assertSame('pending', $payment->status);
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/interface/initiatetransaction')
            && ($request->data()['amount'] ?? null) === '25.00'
            && PaynowHash::valid($request->data(), $key));

        $callback = $this->signed([
            'reference' => $reservation->id, 'paynowreference' => 'PN-123', 'amount' => '25.00',
            'status' => 'Awaiting Delivery', 'pollurl' => $pollUrl,
        ], $key);
        $this->call('POST', '/api/paynow/result', [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($callback))->assertNoContent();
        $this->call('POST', '/api/paynow/result', [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($callback))->assertNoContent();

        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('2.0000', (string) StockLevel::firstOrFail()->quantity_reserved);
        $this->assertSame(1, WebhookEvent::count());
        Http::assertSentCount(2);
    }

    public function test_paynow_rejects_tampered_callbacks_and_late_payment_does_not_reclaim_released_stock(): void
    {
        $key = 'integration-secret-for-tests';
        config([
            'app.url' => 'https://store.example.test', 'paynow.enabled' => true,
            'paynow.integration_id' => '12345', 'paynow.integration_key' => $key, 'paynow.currency' => 'USD',
        ]);
        $created = app(ReservationService::class)->create($this->branch, $this->product, [
            'quantity' => '5', 'name' => 'Paynow Customer', 'phone' => '123',
        ], (string) Str::uuid());
        $pollUrl = 'https://www.paynow.co.zw/Interface/CheckPayment/?guid=late';
        $initReply = $this->signed(['status' => 'Ok', 'browserurl' => 'https://www.paynow.co.zw/Payment/ConfirmPayment/123', 'pollurl' => $pollUrl], $key);
        $paidReply = $this->signed([
            'reference' => $created['reservation']->id, 'amount' => '62.50', 'status' => 'Paid', 'pollurl' => $pollUrl,
        ], $key);
        Http::fake([
            'https://www.paynow.co.zw/interface/initiatetransaction' => Http::response(http_build_query($initReply), 200),
            'https://www.paynow.co.zw/Interface/CheckPayment*' => Http::response(http_build_query($paidReply), 200),
        ]);
        app(PaynowGateway::class)->start($created['reservation'], $created['token']);
        $payment = ReservationPayment::firstOrFail();

        $tampered = ['reference' => $created['reservation']->id, 'amount' => '62.50', 'status' => 'Paid', 'pollurl' => $pollUrl, 'hash' => str_repeat('0', 128)];
        $this->expectException(ConflictHttpException::class);
        try {
            app(PaynowGateway::class)->handleCallback($tampered);
        } finally {
            $this->assertSame('pending', $payment->fresh()->status);
        }
    }

    public function test_paynow_out_of_order_paid_event_after_cancellation_is_flagged_for_review(): void
    {
        $key = 'integration-secret-for-tests';
        config([
            'app.url' => 'https://store.example.test', 'paynow.enabled' => true,
            'paynow.integration_id' => '12345', 'paynow.integration_key' => $key, 'paynow.currency' => 'USD',
        ]);
        $created = app(ReservationService::class)->create($this->branch, $this->product, [
            'quantity' => '5', 'name' => 'Out of Order Customer', 'phone' => '123',
        ], (string) Str::uuid());
        $pollUrl = 'https://www.paynow.co.zw/Interface/CheckPayment/?guid=out-of-order';
        $initReply = $this->signed(['status' => 'Ok', 'browserurl' => 'https://www.paynow.co.zw/Payment/ConfirmPayment/456', 'pollurl' => $pollUrl], $key);
        $events = [
            $this->signed(['reference' => $created['reservation']->id, 'amount' => '62.50', 'status' => 'Cancelled', 'pollurl' => $pollUrl], $key),
            $this->signed(['reference' => $created['reservation']->id, 'amount' => '62.50', 'status' => 'Paid', 'pollurl' => $pollUrl], $key),
        ];
        Http::fake(function (HttpRequest $request) use ($initReply, &$events) {
            if (str_contains($request->url(), '/initiatetransaction')) {
                return Http::response(http_build_query($initReply), 200);
            }

            return Http::response(http_build_query(array_shift($events)), 200);
        });
        app(PaynowGateway::class)->start($created['reservation'], $created['token']);

        foreach (['Cancelled', 'Paid'] as $status) {
            $callback = $this->signed([
                'reference' => $created['reservation']->id, 'amount' => '62.50', 'status' => $status, 'pollurl' => $pollUrl,
            ], $key);
            app(PaynowGateway::class)->handleCallback($callback);
        }

        $this->assertSame('cancelled', $created['reservation']->fresh()->status);
        $this->assertSame('paid_late', ReservationPayment::firstOrFail()->status);
        $this->assertSame('0.0000', (string) StockLevel::firstOrFail()->quantity_reserved);
        $this->assertDatabaseHas('audit_logs', ['action' => 'reservation.payment.late_review', 'branch_id' => $this->branch->id]);
    }

    public function test_paynow_hash_matches_official_outbound_example(): void
    {
        $values = [
            'id' => '1201', 'reference' => 'TEST REF', 'amount' => '99.99',
            'additionalinfo' => 'A test ticket transaction',
            'returnurl' => 'http://www.google.com/search?q=returnurl',
            'resulturl' => 'http://www.google.com/search?q=resulturl',
            'status' => 'Message',
        ];

        $this->assertSame(
            '2A033FC38798D913D42ECB786B9B19645ADEDBDE788862032F1BD82CF3B92DEF84F316385D5B40DBB35F1A4FD7D5BFE73835174136463CDD48C9366B0749C689',
            PaynowHash::make($values, '3e9fed89-60e1-4ce5-ab6e-6b1eb2d4f977'),
        );

        $incoming = [
            'status' => 'Ok',
            'browserurl' => 'https://staging.paynow.co.zw/Payment/ConfirmPayment/9510',
            'pollurl' => 'https://staging.paynow.co.zw/Interface/CheckPayment/?guid=c7ed41da-0159-46da-b428-69549f770413',
            'paynowreference' => '9510',
            'hash' => '750DD0B0DF374678707BB5AF915AF81C228B9058AD57BB7120569EC68BBB9C2EFC1B26C6375D2BC562AC909B3CD6B2AF1D42E1A5E479FFAC8F4FB3FDCE71DF4D',
        ];
        $this->assertTrue(PaynowHash::valid($incoming, '3e9fed89-60e1-4ce5-ab6e-6b1eb2d4f977'));
    }

    private function signed(array $values, string $key): array
    {
        return [...$values, 'hash' => PaynowHash::make($values, $key)];
    }
}
