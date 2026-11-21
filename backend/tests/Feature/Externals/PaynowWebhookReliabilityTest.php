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
use App\Models\WebhookEvent;
use App\Support\PaynowGateway;
use App\Support\PaynowHash;
use App\Support\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class PaynowWebhookReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Product $product;

    private ?string $logPath = null;

    protected function setUp(): void
    {
        parent::setUp();
        $platform = Platform::create(['name' => 'TMS', 'slug' => 'tms-'.uniqid(), 'currency_code' => 'USD']);
        $this->branch = Branch::create([
            'platform_id' => $platform->id,
            'name' => 'External Branch',
            'code' => 'EXT-'.strtoupper(bin2hex(random_bytes(2))),
            'slug' => 'external-'.uniqid(),
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
        $category = Category::create(['branch_id' => $this->branch->id, 'name' => 'Filters', 'slug' => 'filters', 'is_active' => true]);
        $this->product = Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'sku' => 'FILTER-EXT',
            'name' => 'External Filter',
            'description' => 'Private description',
            'selling_price_minor' => 1250,
            'currency_code' => 'USD',
            'track_stock' => true,
            'is_active' => true,
            'public_slug' => 'external-filter',
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

    protected function tearDown(): void
    {
        if ($this->logPath !== null && is_file($this->logPath)) {
            unlink($this->logPath);
        }

        parent::tearDown();
    }

    public function test_replayed_signed_callback_does_not_poll_the_provider_twice(): void
    {
        $key = 'stage84-integration-key';
        $this->configurePaynow($key);
        $created = $this->createReservation();
        $pollUrl = 'https://www.paynow.co.zw/Interface/CheckPayment/?guid=replay-stage84';
        $initReply = $this->signed([
            'status' => 'Ok',
            'browserurl' => 'https://www.paynow.co.zw/Payment/ConfirmPayment/replay-stage84',
            'pollurl' => $pollUrl,
        ], $key);
        $paidReply = $this->signed([
            'reference' => $created['reservation']->id,
            'paynowreference' => 'PN-REPLAY',
            'amount' => '25.00',
            'status' => 'Awaiting Delivery',
            'pollurl' => $pollUrl,
        ], $key);
        Http::fake([
            'https://www.paynow.co.zw/interface/initiatetransaction' => Http::response(http_build_query($initReply), 200),
            'https://www.paynow.co.zw/Interface/CheckPayment*' => Http::response(http_build_query($paidReply), 200),
        ]);
        Http::preventStrayRequests();
        app(PaynowGateway::class)->start($created['reservation'], $created['token']);
        $callback = $this->signed([
            'reference' => $created['reservation']->id,
            'paynowreference' => 'PN-REPLAY',
            'amount' => '25.00',
            'status' => 'Awaiting Delivery',
            'pollurl' => $pollUrl,
        ], $key);

        $first = $this->postWebhook($callback);
        $second = $this->postWebhook($callback);

        $first->assertNoContent();
        $second->assertNoContent();
        $this->assertSame('confirmed', $created['reservation']->fresh()->status);
        $this->assertSame('paid', ReservationPayment::firstOrFail()->status);
        $this->assertSame(1, WebhookEvent::count());
        Http::assertSentCount(2);
        Http::assertSent(fn (HttpRequest $request): bool => ! str_contains($request->url(), 'initiatetransaction'));
    }

    public function test_invalid_signature_returns_400_without_provider_call_or_webhook_event(): void
    {
        $key = 'stage84-integration-key';
        $this->configurePaynow($key);
        $path = $this->useTemporaryStructuredLog();
        $created = $this->createReservation();
        $pollUrl = 'https://www.paynow.co.zw/Interface/CheckPayment/?guid=signature-stage84';
        $initReply = $this->signed([
            'status' => 'Ok',
            'browserurl' => 'https://www.paynow.co.zw/Payment/ConfirmPayment/signature-stage84',
            'pollurl' => $pollUrl,
        ], $key);
        Http::fake(['https://www.paynow.co.zw/interface/initiatetransaction' => Http::response(http_build_query($initReply), 200)]);
        app(PaynowGateway::class)->start($created['reservation'], $created['token']);
        Http::fake();
        Http::preventStrayRequests();
        $tampered = [
            'reference' => $created['reservation']->id,
            'paynowreference' => 'PN-TAMPER',
            'amount' => '25.00',
            'status' => 'Paid',
            'pollurl' => $pollUrl,
            'hash' => str_repeat('0', 128),
        ];

        $this->postWebhook($tampered)->assertStatus(400);

        Http::assertNothingSent();
        $this->assertSame(0, WebhookEvent::count());
        $this->assertSame('pending', ReservationPayment::firstOrFail()->status);
        $log = (string) file_get_contents($path);
        $this->assertStringContainsString('paynow.webhook.rejected', $log);
        $this->assertStringContainsString('invalid_signature', $log);
        $this->assertStringNotContainsString($key, $log);
    }

    public function test_poll_retries_are_bounded_by_configuration(): void
    {
        $key = 'stage84-integration-key';
        config(['paynow.poll_retries' => 2]);
        $this->configurePaynow($key);
        $created = $this->createReservation();
        $pollUrl = 'https://www.paynow.co.zw/Interface/CheckPayment/?guid=retry-stage84';
        $initReply = $this->signed([
            'status' => 'Ok',
            'browserurl' => 'https://www.paynow.co.zw/Payment/ConfirmPayment/retry-stage84',
            'pollurl' => $pollUrl,
        ], $key);
        Http::fake(['https://www.paynow.co.zw/interface/initiatetransaction' => Http::response(http_build_query($initReply), 200)]);
        app(PaynowGateway::class)->start($created['reservation'], $created['token']);
        Http::fake(['https://www.paynow.co.zw/Interface/CheckPayment*' => Http::response('gateway unavailable', 503)]);
        Http::preventStrayRequests();
        $callback = $this->signed([
            'reference' => $created['reservation']->id,
            'paynowreference' => 'PN-RETRY',
            'amount' => '25.00',
            'status' => 'Paid',
            'pollurl' => $pollUrl,
        ], $key);

        try {
            app(PaynowGateway::class)->handleCallback($callback);
            $this->fail('Expected the failed status poll to be rejected.');
        } catch (ConflictHttpException) {
        }

        Http::assertSentCount(3);
        $this->assertSame(0, WebhookEvent::count());
        $this->assertSame('pending', ReservationPayment::firstOrFail()->status);
    }

    /** @param array<string, string> $callback */
    private function postWebhook(array $callback): TestResponse
    {
        return $this->call(
            'POST',
            '/api/paynow/result',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            http_build_query($callback),
        );
    }

    /** @return array{reservation: Reservation, token: string} */
    private function createReservation(): array
    {
        return app(ReservationService::class)->create($this->branch, $this->product, [
            'quantity' => '2',
            'name' => 'Webhook Customer',
            'phone' => '+263771234567',
        ], (string) Str::uuid());
    }

    private function configurePaynow(string $key): void
    {
        config([
            'app.url' => 'https://store.example.test',
            'paynow.enabled' => true,
            'paynow.integration_id' => '12345',
            'paynow.integration_key' => $key,
            'paynow.currency' => 'USD',
        ]);
    }

    /** @param array<string, string> $values @return array<string, string> */
    private function signed(array $values, string $key): array
    {
        return [...$values, 'hash' => PaynowHash::make($values, $key)];
    }

    private function useTemporaryStructuredLog(): string
    {
        $this->logPath = storage_path('logs/stage84-webhook-'.Str::uuid().'.log');
        $channel = 'stage84_webhook_'.str_replace('-', '_', (string) Str::uuid());
        config([
            'logging.channels.'.$channel => [
                'driver' => 'single',
                'path' => $this->logPath,
                'level' => 'debug',
                'replace_placeholders' => true,
            ],
            'observability.log_channel' => $channel,
            'observability.failed_job_channel' => $channel,
        ]);

        return $this->logPath;
    }
}
