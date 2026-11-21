<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Platform;
use App\Models\ReportExport;
use App\Models\ReservationPayment;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\PaynowHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $adminA;

    private User $adminB;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');
        $this->branchB = $this->branch($this->platform, 'BTB');

        $this->adminA = $this->user('Admin A');
        $this->adminB = $this->user('Admin B');
        $this->platformAdmin = User::factory()->platformAdmin()->create();

        $this->membership($this->branchA, $this->adminA, 'admin');
        $this->membership($this->branchB, $this->adminB, 'admin');
    }

    public function test_platform_admin_without_membership_cannot_read_branch_data(): void
    {
        $this->withAuthToken($this->platformAdmin)
            ->getJson("/api/branches/{$this->branchA->id}/products")
            ->assertForbidden();
    }

    public function test_branch_user_cannot_access_another_branch(): void
    {
        $this->withAuthToken($this->adminA)
            ->getJson("/api/branches/{$this->branchB->id}/products")
            ->assertForbidden();
    }

    public function test_suspended_branch_rejects_writes(): void
    {
        $this->branchA->update(['status' => Branch::STATUS_SUSPENDED]);

        $this->withAuthToken($this->adminA)
            ->getJson("/api/branches/{$this->branchA->id}/products")
            ->assertOk();

        $this->withAuthToken($this->adminA)
            ->postJson("/api/branches/{$this->branchA->id}/products", [
                'name' => 'Blocked',
                'sku' => 'BLOCKED-1',
                'base_unit_id' => '01',
                'selling_price' => '1.00',
            ])
            ->assertForbidden();
    }

    public function test_audit_logs_are_immutable(): void
    {
        $log = AuditLog::create([
            'branch_id' => $this->branchA->id,
            'user_id' => $this->adminA->id,
            'action' => 'test.action',
            'entity_type' => 'test',
            'entity_id' => '1',
        ]);

        try {
            $log->update(['action' => 'changed']);
            $this->fail('Expected audit log update to be blocked.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit logs are immutable.', $exception->getMessage());
        }

        try {
            $log->delete();
            $this->fail('Expected audit log deletion to be blocked.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit logs are immutable.', $exception->getMessage());
        }
    }

    public function test_sensitive_values_are_redacted_in_audit_logs(): void
    {
        $logger = app(AuditLogger::class);

        $logger->log(
            'test.sensitive',
            'test',
            ['password' => 'secret', 'token' => 'abc'],
            ['card_number' => '4111', 'safe' => 'value'],
            $this->branchA->id,
            $this->adminA,
        );

        $log = AuditLog::where('action', 'test.sensitive')->firstOrFail();

        $this->assertSame('[redacted]', $log->before['password']);
        $this->assertSame('[redacted]', $log->before['token']);
        $this->assertSame('[redacted]', $log->after['card_number']);
        $this->assertSame('value', $log->after['safe']);
    }

    public function test_inactive_user_with_valid_token_is_rejected(): void
    {
        $this->adminA->update(['is_active' => false]);

        $this->withAuthToken($this->adminA)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_revoked_token_is_rejected(): void
    {
        $token = $this->tokenFor($this->adminA);
        $this->adminA->tokens()->delete();

        $this->withRawToken($token)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_user_without_branch_permission_cannot_update_product(): void
    {
        $cashier = $this->user('Cashier');
        $this->membership($this->branchA, $cashier, 'cashier');

        $product = $this->product(
            $this->branchA,
            $this->unit($this->branchA, 'SEC'),
            sku: 'SEC-1',
            name: 'Original Name',
        );

        $this->withAuthToken($cashier)
            ->patchJson("/api/branches/{$this->branchA->id}/products/{$product->id}", [
                'name' => 'Unauthorized Name',
            ])
            ->assertForbidden();

        $this->assertSame('Original Name', $product->fresh()->name);
    }

    public function test_customer_update_ignores_branch_id_mass_assignment_attempt(): void
    {
        $customer = Customer::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Original Customer',
            'phone' => '263700000001',
            'is_active' => true,
        ]);

        $this->withAuthToken($this->adminA)
            ->patchJson("/api/branches/{$this->branchA->id}/customers/{$customer->id}", [
                'name' => 'Updated Customer',
                'branch_id' => $this->branchB->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Customer');

        $customer->refresh();

        $this->assertSame($this->branchA->id, $customer->branch_id);
        $this->assertSame(0, Customer::query()->where('branch_id', $this->branchB->id)->count());
    }

    public function test_product_update_ignores_unknown_price_field(): void
    {
        $product = $this->product(
            $this->branchA,
            $this->unit($this->branchA, 'UNKNOWN'),
            sku: 'UNKNOWN-1',
            name: 'Original Name',
        );

        $this->withAuthToken($this->adminA)
            ->patchJson("/api/branches/{$this->branchA->id}/products/{$product->id}", [
                'name' => 'Updated Name',
                'selling_price_minor' => 1,
            ])
            ->assertOk();

        $product->refresh();

        $this->assertSame('Updated Name', $product->name);
        $this->assertSame(1000, $product->selling_price_minor);
    }

    public function test_public_product_escapes_script_payloads(): void
    {
        $payload = '<script>alert(1)</script>';
        $product = $this->product(
            $this->branchA,
            $this->unit($this->branchA, 'XSS'),
            sku: 'XSS-1',
            name: $payload,
        );
        $product->update([
            'public_slug' => 'xss-product',
            'public_description' => $payload,
            'is_public' => true,
        ]);
        $this->branchA->update(['status' => Branch::STATUS_ACTIVE]);
        config()->set('catalog.public_branch_slug', $this->branchA->slug);

        $response = $this->get('/shop/xss-product')->assertOk();

        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertSee('\u003Cscript\u003E', false);
        $response->assertDontSee($payload, false);
    }

    public function test_export_requires_platform_permission_and_records_audit(): void
    {
        $this->withAuthToken($this->adminA)
            ->postJson("/api/platforms/{$this->platform->id}/reports/export")
            ->assertForbidden();

        $this->assertSame(0, ReportExport::query()->count());

        Storage::fake('local');

        $this->withAuthToken($this->platformAdmin)
            ->postJson("/api/platforms/{$this->platform->id}/reports/export")
            ->assertCreated();

        $export = ReportExport::query()->firstOrFail();
        $audit = AuditLog::query()->where('action', 'report.exported')->firstOrFail();

        $this->assertSame($this->platform->id, $export->scope_id);
        $this->assertSame($this->platformAdmin->id, $audit->user_id);
        $this->assertNull($audit->branch_id);
    }

    public function test_paynow_result_rejects_callback_with_invalid_signature(): void
    {
        config()->set('paynow.enabled', true);
        config()->set('paynow.integration_id', 'test-integration');
        config()->set('paynow.integration_key', 'correct-test-key');
        config()->set('paynow.currency', 'USD');

        Http::preventStrayRequests();

        $callback = [
            'reference' => '999999',
            'amount' => '10.00',
            'status' => 'Paid',
            'pollurl' => 'https://www.paynow.co.zw/interface/remotetransaction',
        ];
        $callback['hash'] = PaynowHash::make($callback, 'wrong-test-key');

        $response = $this->call(
            'POST',
            '/api/paynow/result',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            http_build_query($callback),
        );

        $response->assertStatus(400);
        $this->assertSame('Invalid Paynow result.', $response->getContent());
        $this->assertSame(0, ReservationPayment::query()->count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'reservation.payment.status_changed']);
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $email = 'rate-limit-'.uniqid().'@example.test';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', ['email' => $email, 'password' => 'wrong-password'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('email');
        }

        $response = $this->postJson('/login', ['email' => $email, 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertStringContainsString('seconds', $response->json('errors.email.0'));
    }
}
