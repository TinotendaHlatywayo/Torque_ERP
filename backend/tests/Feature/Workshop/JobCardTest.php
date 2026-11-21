<?php

namespace Tests\Feature\Workshop;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Platform;
use App\Models\Product;
use App\Models\Sale;
use App\Models\ServiceJob;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkshopService;
use App\Support\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\Feature\Catalogue\InteractsWithCatalogue;
use Tests\TestCase;

class JobCardTest extends TestCase
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
        $this->branchA = $this->branch($this->platform, 'JOB-A');
        $this->branchB = $this->branch($this->platform, 'JOB-B');
        $this->admin = $this->user('Job Admin A');
        $this->viewer = $this->user('Job Viewer A');
        $this->adminB = $this->user('Job Admin B');

        $this->membership($this->branchA, $this->admin, 'admin');
        $this->membership($this->branchA, $this->viewer, 'viewer');
        $this->membership($this->branchB, $this->adminB, 'admin');
    }

    public function test_job_state_transitions_from_estimate_to_approval_to_progress_to_completion(): void
    {
        $unit = $this->unit($this->branchA);
        $customer = $this->customer($this->branchA);
        $product = $this->stockedProduct($this->branchA, $unit, 'JOB-TRANS', $this->admin);
        $service = $this->workshopService($this->branchA, 'SVC-TRANS');
        $job = $this->job($this->branchA, $customer, $this->admin, $this->admin->id);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/labour", [
                'description' => 'Diagnosis and repair',
                'hours' => '1.50',
                'rate_minor' => 1000,
            ])->assertCreated();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/labour", [
                'workshop_service_id' => $service->id,
            ])->assertCreated();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/parts", [
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'quantity' => '2',
            ])->assertCreated();

        $estimated = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/estimate")
            ->assertOk()
            ->json('data');

        $this->assertSame('estimated', $estimated['status']);
        $this->assertSame(4500, $estimated['estimate_minor']);

        $approved = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/approve", [
                'approval_reference' => 'PO-TRANS-1',
                'approval_channel' => 'email',
            ])->assertOk()->json('data');

        $this->assertSame('approved', $approved['status']);
        $this->assertNotNull($approved['approved_at']);
        $this->assertSame('PO-TRANS-1', $approved['approval_reference']);

        $started = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/start")
            ->assertOk()
            ->json('data');
        $this->assertSame('in_progress', $started['status']);

        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}", ['diagnosis' => 'Worn brake pads'])
            ->assertOk();

        $completed = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/complete")
            ->assertOk()
            ->json('data');

        $this->assertSame('completed', $completed['status']);
        $this->assertNotNull($completed['completed_at']);
        $this->assertSame(0, Sale::count());
    }

    public function test_unauthorized_transition_rejection(): void
    {
        $customer = $this->customer($this->branchA);

        $this->withAuthToken($this->viewer)
            ->postJson("/api/branches/{$this->branchA->id}/jobs", $this->jobPayload($customer))
            ->assertForbidden();

        $job = $this->job($this->branchA, $customer, $this->admin);

        $this->withAuthToken($this->viewer)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/complete")
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/complete")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/start")
            ->assertStatus(422);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/labour", [
                'description' => 'Inspection',
                'hours' => '1',
                'rate_minor' => 500,
            ])->assertCreated();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/estimate")
            ->assertOk();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/start")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_labour_lines_are_recorded_and_total_is_calculated(): void
    {
        $service = $this->workshopService($this->branchA, 'SVC-LABOUR', '2.00', 1250);
        $job = $this->job($this->branchA, $this->customer($this->branchA), $this->admin);

        $first = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/labour", [
                'workshop_service_id' => $service->id,
            ])->assertCreated()->json('data');

        $this->assertSame('Brake service', $first['description']);
        $this->assertSame('2.00', $first['hours']);
        $this->assertSame(2500, $first['amount_minor']);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/labour", [
                'description' => 'Additional fitting',
                'hours' => '0.50',
                'rate_minor' => 1000,
            ])->assertCreated();

        $estimate = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/estimate")
            ->assertOk()
            ->json('data');

        $this->assertSame(3000, $estimate['estimate_minor']);
        $this->assertSame(2, $estimate['line_counts']['labour']);
    }

    public function test_parts_consumption_posts_exactly_one_canonical_movement_and_is_idempotent_on_retry(): void
    {
        $unit = $this->unit($this->branchA);
        $product = $this->stockedProduct($this->branchA, $unit, 'JOB-PARTS', $this->admin);
        $job = $this->job($this->branchA, $this->customer($this->branchA), $this->admin);
        $payload = [
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => '2',
            'unit_price_minor' => 1000,
        ];

        $first = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/parts", $payload, ['Idempotency-Key' => 'job-parts-1'])
            ->assertCreated();

        $this->assertFalse($first->json('idempotent_replay'));
        $lineId = $first->json('data.id');

        $retry = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/parts", $payload, ['Idempotency-Key' => 'job-parts-1'])
            ->assertCreated();

        $this->assertTrue($retry->json('idempotent_replay'));
        $this->assertSame($lineId, $retry->json('data.id'));

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/parts", ['quantity' => '3'], ['Idempotency-Key' => 'job-parts-1'])
            ->assertStatus(409);

        $movements = StockMovement::where('branch_id', $this->branchA->id)
            ->where('source', StockMovement::SOURCE_JOB)
            ->where('source_id', $lineId)
            ->get();

        $this->assertCount(1, $movements);
        $this->assertSame(StockMovement::TYPE_JOB_CONSUMPTION, $movements->first()->movement_type);
        $this->assertSame('-2.0000', (string) $movements->first()->quantity);
        $this->assertSame(1, $job->partLines()->count());
        $this->assertSame('8.0000', (string) $product->stockLevel()->first()->quantity_on_hand);
    }

    public function test_cancel_and_reopen_do_not_reverse_stock_or_invoice(): void
    {
        $unit = $this->unit($this->branchA);
        $customer = $this->customer($this->branchA);
        $product = $this->stockedProduct($this->branchA, $unit, 'JOB-CANCEL', $this->admin);
        $job = $this->job($this->branchA, $customer, $this->admin, $this->admin->id);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/parts", [
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'quantity' => '2',
            ])->assertCreated();

        $invoice = $this->invoice($this->branchA, $customer);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/estimate")
            ->assertOk();
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/approve", ['approval_reference' => 'PO-CANCEL'])
            ->assertOk();
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/start")
            ->assertOk();
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/invoice", ['sale_id' => $invoice->id])
            ->assertOk();

        $cancelled = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/cancel", ['reason' => 'Customer postponed the work'])
            ->assertOk()
            ->json('data');

        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertSame('in_progress', $cancelled['previous_status']);
        $this->assertSame($invoice->id, $cancelled['sale_id']);
        $this->assertSame('8.0000', (string) $product->stockLevel()->first()->quantity_on_hand);
        $this->assertSame(0, StockMovement::where('movement_type', StockMovement::TYPE_REVERSAL)->count());
        $this->assertSame(1, Sale::count());

        $reopened = $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/reopen")
            ->assertOk()
            ->json('data');

        $this->assertSame('in_progress', $reopened['status']);
        $this->assertSame($invoice->id, $reopened['sale_id']);
        $this->assertSame('8.0000', (string) $product->stockLevel()->first()->quantity_on_hand);
        $this->assertSame(0, StockMovement::where('movement_type', StockMovement::TYPE_REVERSAL)->count());
        $this->assertSame(1, Sale::count());
    }

    public function test_branch_isolation_for_jobs_services_and_invoices(): void
    {
        $jobA = $this->job($this->branchA, $this->customer($this->branchA), $this->admin);
        $jobB = $this->job($this->branchB, $this->customer($this->branchB), $this->adminB);
        $serviceB = $this->workshopService($this->branchB, 'SVC-B');
        $invoiceB = $this->invoice($this->branchB, $this->customer($this->branchB, 'Branch B customer'));

        $list = $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/jobs")
            ->assertOk()
            ->json('data');

        $this->assertSame([$jobA->id], array_column($list, 'id'));

        $this->withAuthToken($this->admin)
            ->getJson("/api/branches/{$this->branchA->id}/jobs/{$jobB->id}")
            ->assertNotFound();

        $this->withAuthToken($this->adminB)
            ->getJson("/api/branches/{$this->branchA->id}/jobs/{$jobA->id}")
            ->assertForbidden();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$jobA->id}/labour", [
                'workshop_service_id' => $serviceB->id,
            ])->assertStatus(422);

        $this->postJson("/api/branches/{$this->branchA->id}/jobs/{$jobA->id}/invoice", ['sale_id' => $invoiceB->id])
            ->assertStatus(422);
    }

    public function test_customer_approval_is_required_before_start(): void
    {
        $job = $this->job($this->branchA, $this->customer($this->branchA), $this->admin, $this->admin->id);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/labour", [
                'description' => 'Inspection',
                'hours' => '1',
                'rate_minor' => 500,
            ])->assertCreated();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/estimate")
            ->assertOk();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/approve", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('approval_reference');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/start")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/approve", ['approval_reference' => 'PO-APPROVAL'])
            ->assertOk();

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_no_invoice_is_generated_automatically(): void
    {
        $customer = $this->customer($this->branchA);
        $job = $this->job($this->branchA, $customer, $this->admin, $this->admin->id);

        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/labour", [
                'description' => 'Repair',
                'hours' => '1',
                'rate_minor' => 1000,
            ])->assertCreated();
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/estimate")->assertOk();
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/approve", ['approval_reference' => 'PO-NO-INVOICE'])->assertOk();
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/start")->assertOk();
        $this->withAuthToken($this->admin)
            ->patchJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}", ['diagnosis' => 'Resolved'])->assertOk();
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/complete")->assertOk();

        $this->assertSame(0, Sale::count());

        $invoice = $this->invoice($this->branchA, $customer);
        $this->withAuthToken($this->admin)
            ->postJson("/api/branches/{$this->branchA->id}/jobs/{$job->id}/invoice", ['sale_id' => $invoice->id])
            ->assertOk()
            ->assertJsonPath('data.invoice.id', $invoice->id);

        $this->assertSame(1, Sale::count());
    }

    private function customer(Branch $branch, string $name = 'Job Customer'): Customer
    {
        return Customer::create([
            'branch_id' => $branch->id,
            'name' => $name,
            'phone' => '0770000000',
            'is_active' => true,
        ]);
    }

    private function stockedProduct(Branch $branch, Unit $unit, string $sku, User $actor): Product
    {
        $product = $this->product($branch, $unit, null, $sku, 'Product '.$sku, null, 1000);
        app(StockLedger::class)->postOpening(
            $branch,
            $product,
            '10',
            $unit->id,
            'Test opening',
            $actor,
            'opening-'.$sku,
        );

        return $product->refresh();
    }

    private function workshopService(
        Branch $branch,
        string $code,
        string $hours = '1.00',
        int $rateMinor = 1000,
    ): WorkshopService {
        return WorkshopService::create([
            'branch_id' => $branch->id,
            'code' => $code,
            'name' => 'Brake service',
            'description' => 'Inspect and replace brake pads',
            'default_hours' => $hours,
            'default_rate_minor' => $rateMinor,
            'currency_code' => $branch->currency_code,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPayload(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'vehicle_make' => 'Toyota',
            'vehicle_model' => 'Corolla',
            'vehicle_registration' => 'AD-1234',
            'vehicle_year' => '2018',
            'complaint' => 'Brakes squeal when stopping',
        ];
    }

    private function job(Branch $branch, Customer $customer, User $actor, ?int $technicianId = null): ServiceJob
    {
        $response = $this->withAuthToken($actor)
            ->postJson("/api/branches/{$branch->id}/jobs", [
                ...$this->jobPayload($customer),
                'technician_id' => $technicianId,
            ])->assertCreated();

        return ServiceJob::findOrFail($response->json('data.id'));
    }

    private function invoice(Branch $branch, Customer $customer): Sale
    {
        return Sale::create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'sale_number' => 'JOB-INV-'.strtoupper(uniqid()),
            'status' => 'posted',
            'payment_status' => 'unpaid',
            'currency_code' => $branch->currency_code,
            'subtotal_minor' => 1000,
            'total_minor' => 1000,
            'idempotency_key' => 'job-inv-'.uniqid(),
            'request_hash' => hash('sha256', 'manual-job-invoice'),
        ]);
    }
}
