<?php

namespace Tests\Feature;

use App\Jobs\SendReservationNotification;
use App\Mail\ReservationStatusMail;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Category;
use App\Models\Platform;
use App\Models\Product;
use App\Models\ReservationNotificationDelivery;
use App\Models\StockLevel;
use App\Models\Unit;
use App\Models\User;
use App\Support\ReservationNotificationService;
use App\Support\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_reservation_queues_one_delivery_for_the_customer_and_each_authorized_branch_user(): void
    {
        Queue::fake([SendReservationNotification::class]);
        [$branch, $product] = $this->reservableProduct();
        $manager = User::factory()->create();
        BranchUser::create(['branch_id' => $branch->id, 'user_id' => $manager->id, 'role' => 'manager', 'permissions' => [], 'is_active' => true]);
        $cashier = User::factory()->create();
        BranchUser::create(['branch_id' => $branch->id, 'user_id' => $cashier->id, 'role' => 'cashier', 'permissions' => [], 'is_active' => true]);

        $created = app(ReservationService::class)->create($branch, $product, [
            'quantity' => '1', 'name' => 'Customer', 'email' => 'customer@example.test',
        ], (string) Str::uuid());
        app(ReservationService::class)->create($branch, $product, [
            'quantity' => '1', 'name' => 'Customer', 'email' => 'customer@example.test',
        ], $created['reservation']->idempotency_key);

        $this->assertDatabaseCount('reservation_notification_deliveries', 2);
        $this->assertDatabaseHas('reservation_notification_deliveries', [
            'reservation_id' => $created['reservation']->id,
            'event' => ReservationNotificationService::EVENT_CREATED,
            'recipient_key' => 'customer',
            'status' => ReservationNotificationDelivery::STATUS_QUEUED,
        ]);
        $this->assertDatabaseHas('reservation_notification_deliveries', [
            'reservation_id' => $created['reservation']->id,
            'event' => ReservationNotificationService::EVENT_CREATED,
            'recipient_key' => 'user:'.$manager->id,
        ]);
        Queue::assertPushed(SendReservationNotification::class, 2);
    }

    public function test_confirming_payment_queues_branch_notification_once_even_when_the_provider_retries(): void
    {
        Queue::fake([SendReservationNotification::class]);
        [$branch, $product] = $this->reservableProduct();
        $manager = User::factory()->create();
        BranchUser::create(['branch_id' => $branch->id, 'user_id' => $manager->id, 'role' => 'manager', 'permissions' => [], 'is_active' => true]);
        $created = app(ReservationService::class)->create($branch, $product, [
            'quantity' => '1', 'name' => 'Customer', 'phone' => '+263771234567',
        ], (string) Str::uuid());

        Queue::fake([SendReservationNotification::class]);
        $this->assertTrue(app(ReservationService::class)->confirmPayment($created['reservation']));
        $this->assertTrue(app(ReservationService::class)->confirmPayment($created['reservation']->fresh()));

        $this->assertDatabaseCount('reservation_notification_deliveries', 2);
        $this->assertDatabaseHas('reservation_notification_deliveries', [
            'reservation_id' => $created['reservation']->id,
            'event' => ReservationNotificationService::EVENT_PAYMENT_CONFIRMED,
            'recipient_key' => 'user:'.$manager->id,
        ]);
        Queue::assertPushed(SendReservationNotification::class, 1);
    }

    public function test_notification_job_sends_once_and_records_a_delivery_status(): void
    {
        Queue::fake([SendReservationNotification::class]);
        Mail::fake();
        [$branch, $product] = $this->reservableProduct();
        $created = app(ReservationService::class)->create($branch, $product, [
            'quantity' => '1', 'name' => 'Customer', 'email' => 'customer@example.test',
        ], (string) Str::uuid());
        $delivery = ReservationNotificationDelivery::query()->where('reservation_id', $created['reservation']->id)->firstOrFail();

        $job = new SendReservationNotification($delivery->id);
        $job->handle();
        $job->handle();

        $this->assertSame(ReservationNotificationDelivery::STATUS_SENT, $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
        Mail::assertSent(ReservationStatusMail::class, fn (ReservationStatusMail $mail): bool => $mail->hasTo('customer@example.test'));
        Mail::assertSent(ReservationStatusMail::class, 1);
    }

    public function test_terminal_notification_failure_is_visible_without_changing_the_reservation(): void
    {
        Queue::fake([SendReservationNotification::class]);
        [$branch, $product] = $this->reservableProduct();
        $created = app(ReservationService::class)->create($branch, $product, [
            'quantity' => '1', 'name' => 'Customer', 'email' => 'customer@example.test',
        ], (string) Str::uuid());
        $delivery = ReservationNotificationDelivery::query()->where('reservation_id', $created['reservation']->id)->firstOrFail();

        (new SendReservationNotification($delivery->id))->failed(new \RuntimeException('Transport unavailable'));

        $this->assertSame(ReservationNotificationDelivery::STATUS_FAILED, $delivery->fresh()->status);
        $this->assertSame('awaiting_payment', $created['reservation']->fresh()->status);
    }

    public function test_notification_job_retries_a_delivery_left_sending_by_an_interrupted_worker(): void
    {
        Queue::fake([SendReservationNotification::class]);
        Mail::fake();
        [$branch, $product] = $this->reservableProduct();
        $created = app(ReservationService::class)->create($branch, $product, [
            'quantity' => '1', 'name' => 'Customer', 'email' => 'customer@example.test',
        ], (string) Str::uuid());
        $delivery = ReservationNotificationDelivery::query()->where('reservation_id', $created['reservation']->id)->firstOrFail();
        $delivery->update(['status' => ReservationNotificationDelivery::STATUS_SENDING, 'attempts' => 1]);

        (new SendReservationNotification($delivery->id))->handle();

        $this->assertSame(ReservationNotificationDelivery::STATUS_SENT, $delivery->fresh()->status);
        $this->assertSame(2, $delivery->fresh()->attempts);
        Mail::assertSent(ReservationStatusMail::class, 1);
    }

    public function test_reservation_mail_escapes_product_and_customer_content(): void
    {
        Queue::fake([SendReservationNotification::class]);
        [$branch, $product] = $this->reservableProduct();
        $product->update(['name' => '<script>alert(1)</script>']);
        $created = app(ReservationService::class)->create($branch, $product, [
            'quantity' => '1', 'name' => '<script>alert(2)</script>', 'email' => 'customer@example.test',
        ], (string) Str::uuid());

        $rendered = (new ReservationStatusMail($created['reservation']->fresh(), ReservationNotificationService::EVENT_CREATED, true))->render();

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $rendered);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $rendered);
        $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $rendered);
        $this->assertStringNotContainsString('<script>alert(2)</script>', $rendered);
    }

    /** @return array{0: Branch, 1: Product} */
    private function reservableProduct(): array
    {
        $platform = Platform::create(['name' => 'TMS', 'slug' => 'tms-'.uniqid(), 'currency_code' => 'USD']);
        $branch = Branch::create([
            'platform_id' => $platform->id, 'name' => 'Rusape Branch', 'code' => 'TMS-'.strtoupper(bin2hex(random_bytes(2))),
            'slug' => 'rusape-'.uniqid(), 'status' => Branch::STATUS_ACTIVE, 'timezone' => 'Africa/Harare', 'currency_code' => 'USD',
        ]);
        $unit = Unit::create(['branch_id' => $branch->id, 'code' => 'EA-'.strtoupper(bin2hex(random_bytes(2))), 'name' => 'Each', 'allow_decimal' => false, 'is_active' => true]);
        $category = Category::create(['branch_id' => $branch->id, 'name' => 'Filters', 'slug' => 'filters-'.uniqid(), 'is_active' => true]);
        $product = Product::create([
            'branch_id' => $branch->id, 'category_id' => $category->id, 'base_unit_id' => $unit->id,
            'sku' => 'FILTER-'.strtoupper(bin2hex(random_bytes(2))), 'name' => 'Air Filter', 'selling_price_minor' => 1250,
            'currency_code' => 'USD', 'track_stock' => true, 'is_active' => true, 'public_slug' => 'air-filter-'.uniqid(),
            'public_description' => 'Customer facing description.', 'is_public' => true,
        ]);
        StockLevel::create(['branch_id' => $branch->id, 'product_id' => $product->id, 'quantity_on_hand' => '5', 'quantity_reserved' => '0', 'reorder_level' => '0']);

        return [$branch, $product];
    }
}
