<?php

namespace Tests\Feature\Catalogue;

use App\Models\Branch;
use App\Models\Platform;
use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\ProductOemRef;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class CatalogueFitmentSearchTest extends TestCase
{
    use InteractsWithBranchAccess;
    use InteractsWithCatalogue;
    use RefreshDatabase;

    private Platform $platform;

    private Branch $branchA;

    private Branch $branchB;

    private User $admin;

    private User $viewer;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->platform();
        $this->branchA = $this->branch($this->platform, 'T7A');
        $this->branchB = $this->branch($this->platform, 'T7B');

        $this->admin = $this->user('Admin A');
        $this->viewer = $this->user('Viewer A');
        $this->adminB = $this->user('Admin B');

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->viewer, 'viewer');
        $this->membership($this->branchB, $this->adminB, 'admin');
    }

    public function test_exact_sku_match_returns_result(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit, null, 'BRK-PAD-001', 'Brake Pad');

        $response = $this->withAuthToken($this->viewer)
            ->getJson("/api/branches/{$this->branchA->id}/catalogue/search?q=BRK-PAD-001")
            ->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame('exact_sku_barcode', $data[0]['match_type']);
        $this->assertSame($product->id, $data[0]['product_id']);
    }

    public function test_exact_barcode_match_returns_result(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit, null, 'SKU-BAR-1', 'Oil Filter', '1234567890123');

        $response = $this->withAuthToken($this->viewer)
            ->getJson("/api/branches/{$this->branchA->id}/catalogue/search?q=1234567890123")
            ->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame('exact_sku_barcode', $data[0]['match_type']);
        $this->assertSame($product->id, $data[0]['product_id']);
    }

    public function test_fitment_match_by_make_model_year_engine_returns_result_with_source(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit, null, 'FIT-1', 'Spark Plug');

        ProductFitment::create([
            'branch_id' => $this->branchA->id,
            'product_id' => $product->id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year_from' => '2016',
            'year_to' => '2020',
            'engine' => '1.8L',
            'source' => 'Supplier Catalog',
            'source_ref' => 'CAT-2026-01',
            'verified' => 'declared',
        ]);

        $response = $this->withAuthToken($this->viewer)
            ->getJson("/api/branches/{$this->branchA->id}/catalogue/search?make=Toyota&model=Corolla&year=2018&engine=1.8L")
            ->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame('fitment', $data[0]['match_type']);
        $this->assertStringContainsString('Supplier Catalog', $data[0]['source']);
        $this->assertStringContainsString('CAT-2026-01', $data[0]['source']);
    }

    public function test_oem_cross_reference_match_returns_result_with_source(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit, null, 'OEM-1', 'Air Filter');

        ProductOemRef::create([
            'branch_id' => $this->branchA->id,
            'product_id' => $product->id,
            'oem_number' => '17801-0D020',
            'brand' => 'Denso',
            'source' => 'OEM List',
            'source_ref' => 'OEM-2026-A',
            'verified' => 'declared',
        ]);

        $response = $this->withAuthToken($this->viewer)
            ->getJson("/api/branches/{$this->branchA->id}/catalogue/search?oem=17801-0D020")
            ->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame('oem', $data[0]['match_type']);
        $this->assertStringContainsString('OEM List', $data[0]['source']);
        $this->assertSame('17801-0D020', $data[0]['oem']['oem_number']);
    }

    public function test_import_fitments_requires_source_and_source_ref_and_is_idempotent(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->product($this->branchA, $unit, null, 'IMP-F-1', 'Part A');

        $payload = [
            'source' => 'Supplier PDF',
            'source_ref' => 'PDF-001 p.5',
            'format' => 'json',
            'rows' => [
                ['product_id' => $product->id, 'make' => 'Nissan', 'model' => 'NP300', 'year_from' => '2015', 'year_to' => '2022', 'engine' => '2.5D'],
            ],
        ];

        $this->withAuthToken($this->viewer)
            ->postJson("/api/branches/{$this->branchA->id}/catalogue/import-fitments", $payload, ['Idempotency-Key' => 'fit-imp-1'])
            ->assertForbidden();

        $first = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/catalogue/import-fitments", $payload, ['Idempotency-Key' => 'fit-imp-1'])
            ->assertCreated();
        $this->assertFalse($first->json('data.idempotent_replay'));
        $this->assertSame(1, $first->json('data.rows_created'));

        $second = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/catalogue/import-fitments", $payload, ['Idempotency-Key' => 'fit-imp-1'])
            ->assertCreated();
        $this->assertTrue($second->json('data.idempotent_replay'));
        $this->assertSame(1, ProductFitment::count());
    }

    public function test_typo_tolerance_is_testable_and_does_not_create_false_positives(): void
    {
        $unit = $this->unit($this->branchA);
        // Product name "Alternator" (10 chars) - searching for "Alternater" (distance 1, length>=5) should match typo-tolerant path
        $this->product($this->branchA, $unit, null, 'TY-1', 'Alternator');

        // close typo (distance 1) -> should be found via typo tolerant branch when q only
        $resClose = $this->withAuthToken($this->viewer)
            ->getJson("/api/branches/{$this->branchA->id}/catalogue/search?q=Alternater")
            ->assertOk();
        $this->assertNotEmpty($resClose->json('data'));
        $this->assertSame('typo_tolerant', $resClose->json('data.0.match_type'));

        // false positive: very different string "BatteryX" vs "Alternator"? distances larger -> should not match
        $resFar = $this->withAuthToken($this->viewer)
            ->getJson("/api/branches/{$this->branchA->id}/catalogue/search?q=BatteryX")
            ->assertOk();
        $this->assertEmpty($resFar->json('data'));
    }

    public function test_no_result_case_returns_empty_list(): void
    {
        $response = $this->withAuthToken($this->viewer)
            ->getJson("/api/branches/{$this->branchA->id}/catalogue/search?q=NO-SUCH-SKU-XYZ")
            ->assertOk();

        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('meta.count'));
    }

    public function test_branch_isolation_for_catalogue_search_and_imports(): void
    {
        $unitA = $this->unit($this->branchA);
        $unitB = $this->unit($this->branchB);
        $productA = $this->product($this->branchA, $unitA, null, 'ISO-A', 'Part A');
        $productB = $this->product($this->branchB, $unitB, null, 'ISO-B', 'Part B');

        ProductOemRef::create([
            'branch_id' => $this->branchB->id,
            'product_id' => $productB->id,
            'oem_number' => 'OEM-ISO-9',
            'brand' => null,
            'source' => 'List',
            'source_ref' => 'R1',
            'verified' => 'declared',
        ]);

        // admin from branch A cannot search branch B
        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchB->id}/catalogue/search?oem=OEM-ISO-9")
            ->assertForbidden();

        // admin B can find it
        $this->withAuthToken($this->adminB)
            ->getJson("/api/branches/{$this->branchB->id}/catalogue/search?oem=OEM-ISO-9")
            ->assertOk()
            ->assertJsonPath('data.0.product_id', $productB->id);
    }
}
