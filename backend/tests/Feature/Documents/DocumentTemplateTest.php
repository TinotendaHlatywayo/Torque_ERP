<?php

namespace Tests\Feature\Documents;

use App\Models\Branch;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class DocumentTemplateTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private User $admin;

    private User $cashier;

    private Product $product;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'BTA');

        $this->admin = $this->user('Admin');
        $this->cashier = $this->user('Cashier');

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->cashier, 'cashier');

        $this->unit = Unit::create([
            'branch_id' => $this->branchA->id,
            'code' => 'PCS',
            'name' => 'Pieces',
            'allow_decimal' => true,
            'is_active' => true,
        ]);
        $this->product = Product::create([
            'branch_id' => $this->branchA->id,
            'base_unit_id' => $this->unit->id,
            'sku' => 'DOC-1',
            'name' => 'Doc Product',
            'selling_price_minor' => 1000,
            'currency_code' => 'USD',
            'track_stock' => true,
            'is_active' => true,
        ]);
        $this->product->stockLevel()->create([
            'branch_id' => $this->branchA->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);
    }

    public function test_template_version_is_created_and_previous_version_deactivated(): void
    {
        $payload = [
            'document_type' => 'receipt',
            'name' => 'Default Receipt',
            'content' => '<h1>{{receipt_number}}</h1><p>{{sale_number}}</p><p>{{total}}</p><p>{{issued_at}}</p>',
        ];

        $first = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/document-templates", $payload)
            ->assertCreated()
            ->assertJsonPath('data.version', 1);

        $second = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/document-templates", [
                ...$payload,
                'content' => '<h1>{{receipt_number}}</h1><p>{{sale_number}}</p><p>{{total}}</p><p>{{issued_at}}</p><p>{{items}}</p>',
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('document_templates', [
            'id' => $first->json('data.id'),
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('document_templates', [
            'id' => $second->json('data.id'),
            'is_active' => true,
        ]);
    }

    public function test_invalid_placeholder_is_rejected(): void
    {
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/document-templates", [
                'document_type' => 'receipt',
                'name' => 'Bad Template',
                'content' => '<p>{{secret_value}}</p>',
            ])
            ->assertStatus(422);
    }

    public function test_missing_required_placeholder_is_rejected(): void
    {
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/document-templates", [
                'document_type' => 'receipt',
                'name' => 'Missing Total',
                'content' => '<p>{{receipt_number}}</p><p>{{issued_at}}</p>',
            ])
            ->assertStatus(422);
    }

    public function test_preview_renders_placeholders(): void
    {
        $templateId = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/document-templates", [
                'document_type' => 'receipt',
                'name' => 'Preview Template',
                'content' => '<h1>{{receipt_number}}</h1><p>{{sale_number}}</p><p>{{total}}</p><p>{{issued_at}}</p>',
            ])
            ->assertCreated()
            ->json('data.id');

        $response = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/document-templates/{$templateId}/preview", [])
            ->assertOk();

        $this->assertStringContainsString('RECEIPT-BTA-000001', $response->json('data.rendered'));
        $this->assertStringContainsString('1045', $response->json('data.rendered'));
    }

    public function test_document_verification_returns_real_sale_target(): void
    {
        $saleId = $this->withAuthToken($this->cashier)
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/branches/{$this->branchA->id}/checkout", [
                'lines' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_id' => $this->unit->id,
                        'quantity' => '1',
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount_minor' => 1000, 'currency_code' => 'USD'],
                ],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->getJson("/api/verify/receipt/{$saleId}")
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('document_id', $saleId);
    }

    public function test_template_permission_denial(): void
    {
        $this->withAuthToken($this->cashier)
            ->postJson("/api/branches/{$this->branchA->id}/document-templates", [
                'document_type' => 'receipt',
                'name' => 'Forbidden',
                'content' => '<p>{{receipt_number}}{{total}}{{issued_at}}</p>',
            ])
            ->assertForbidden();
    }
}
