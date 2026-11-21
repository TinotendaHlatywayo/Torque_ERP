<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveJobRequest;
use App\Http\Requests\LinkJobInvoiceRequest;
use App\Http\Requests\StoreJobLabourRequest;
use App\Http\Requests\StoreJobPartRequest;
use App\Http\Requests\StoreJobRequest;
use App\Http\Requests\UpdateJobRequest;
use App\Models\Branch;
use App\Models\JobLabourLine;
use App\Models\JobPartLine;
use App\Models\ServiceJob;
use App\Support\JobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class JobController extends Controller
{
    public function __construct(private readonly JobService $jobs) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $jobs = $branch->jobs()
            ->with(['customer:id,name', 'technician:id,name', 'invoice:id,sale_number,total_minor,payment_status'])
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->withCount(['labourLines', 'partLines'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $jobs->map(fn (ServiceJob $job): array => $this->payload($job))]);
    }

    public function show(Branch $branch, ServiceJob $job): JsonResponse
    {
        $this->assertBranch($branch, $job);

        $job->load([
            'customer:id,name',
            'technician:id,name',
            'creator:id,name',
            'approver:id,name',
            'invoice:id,sale_number,total_minor,payment_status',
            'labourLines' => fn ($query) => $query->with('workshopService:id,code,name')->orderBy('created_at'),
            'partLines' => fn ($query) => $query->with(['product:id,name,sku', 'unit:id,code'])->orderBy('created_at'),
        ]);

        return response()->json(['data' => $this->payload($job)]);
    }

    public function store(StoreJobRequest $request, Branch $branch): JsonResponse
    {
        $job = $this->jobs->createJob($branch, $request->user(), $request->validated());

        return response()->json(['data' => $this->payload($job)], 201);
    }

    public function update(UpdateJobRequest $request, Branch $branch, ServiceJob $job): JsonResponse
    {
        $this->assertBranch($branch, $job);
        $job = $this->jobs->updateJob($job, $request->user(), $request->validated());

        return response()->json(['data' => $this->payload($job->load(['customer:id,name', 'technician:id,name']))]);
    }

    public function addLabour(StoreJobLabourRequest $request, Branch $branch, ServiceJob $job): JsonResponse
    {
        $this->assertBranch($branch, $job);
        $line = $this->jobs->addLabourLine($job, $request->user(), $request->validated());

        return response()->json(['data' => $this->labourPayload($line)], 201);
    }

    public function addPart(StoreJobPartRequest $request, Branch $branch, ServiceJob $job): JsonResponse
    {
        $this->assertBranch($branch, $job);
        $line = $this->jobs->addPartLine($job, $request->user(), $request->validated(), $this->idempotencyKey($request));

        return response()->json([
            'data' => $this->partPayload($line),
            'idempotent_replay' => $line->idempotentReplay,
        ], 201);
    }

    public function estimate(Request $request, Branch $branch, ServiceJob $job): JsonResponse
    {
        $this->assertBranch($branch, $job);

        return response()->json(['data' => $this->payload($this->jobs->estimate($job, $request->user()))]);
    }

    public function approve(ApproveJobRequest $request, Branch $branch, ServiceJob $job): JsonResponse
    {
        $this->assertBranch($branch, $job);
        $job = $this->jobs->approve($job, $request->user(), $request->validated());

        return response()->json(['data' => $this->payload($job->load(['approver:id,name']))]);
    }

    public function start(Request $request, Branch $branch, ServiceJob $job): JsonResponse
    {
        $this->assertBranch($branch, $job);

        return response()->json(['data' => $this->payload($this->jobs->start($job, $request->user()))]);
    }

    public function complete(Request $request, Branch $branch, ServiceJob $job): JsonResponse
    {
        $this->assertBranch($branch, $job);

        return response()->json(['data' => $this->payload($this->jobs->complete($job, $request->user()))]);
    }

    public function cancel(Request $request, Branch $branch, ServiceJob $job): JsonResponse
    {
        $this->assertBranch($branch, $job);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return response()->json(['data' => $this->payload($this->jobs->cancel($job, $request->user(), $data['reason'] ?? null))]);
    }

    public function reopen(Request $request, Branch $branch, ServiceJob $job): JsonResponse
    {
        $this->assertBranch($branch, $job);

        return response()->json(['data' => $this->payload($this->jobs->reopen($job, $request->user()))]);
    }

    public function linkInvoice(LinkJobInvoiceRequest $request, Branch $branch, ServiceJob $job): JsonResponse
    {
        $this->assertBranch($branch, $job);
        $data = $request->validated();
        $job = $this->jobs->linkInvoice($job, $request->user(), (string) $data['sale_id']);

        return response()->json(['data' => $this->payload($job->load('invoice:id,sale_number,total_minor,payment_status'))]);
    }

    private function assertBranch(Branch $branch, ServiceJob $job): void
    {
        abort_unless($job->branch_id === $branch->id, 404);
    }

    private function idempotencyKey(Request $request): ?string
    {
        $raw = $request->header('Idempotency-Key');

        if ($raw === null) {
            return null;
        }

        $key = trim($raw);

        throw_unless($key !== '' && strlen($key) <= 100, ValidationException::withMessages([
            'idempotency_key' => 'Idempotency-Key must be a non-empty header of at most 100 characters.',
        ]));

        return $key;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ServiceJob $job): array
    {
        return [
            'id' => $job->id,
            'job_number' => $job->job_number,
            'customer_id' => $job->customer_id,
            'customer' => $job->relationLoaded('customer') ? $job->customer : null,
            'technician_id' => $job->technician_id,
            'technician' => $job->relationLoaded('technician') ? $job->technician : null,
            'vehicle_make' => $job->vehicle_make,
            'vehicle_model' => $job->vehicle_model,
            'vehicle_registration' => $job->vehicle_registration,
            'vehicle_year' => $job->vehicle_year,
            'vin' => $job->vin,
            'complaint' => $job->complaint,
            'diagnosis' => $job->diagnosis,
            'status' => $job->status,
            'previous_status' => $job->previous_status,
            'estimate_minor' => $job->estimate_minor,
            'approved_at' => $job->approved_at?->toIso8601String(),
            'approved_by' => $job->approved_by,
            'approver' => $job->relationLoaded('approver') ? $job->approver : null,
            'approval_reference' => $job->approval_reference,
            'approval_channel' => $job->approval_channel,
            'completed_at' => $job->completed_at?->toIso8601String(),
            'cancelled_at' => $job->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $job->cancellation_reason,
            'sale_id' => $job->sale_id,
            'invoice' => $job->relationLoaded('invoice') ? $job->invoice : null,
            'labour_lines' => $job->relationLoaded('labourLines')
                ? $job->labourLines->map(fn (JobLabourLine $line): array => $this->labourPayload($line))->all()
                : [],
            'part_lines' => $job->relationLoaded('partLines')
                ? $job->partLines->map(fn (JobPartLine $line): array => $this->partPayload($line))->all()
                : [],
            'line_counts' => [
                'labour' => $job->labour_lines_count ?? $job->labourLines()->count(),
                'parts' => $job->part_lines_count ?? $job->partLines()->count(),
            ],
            'created_at' => $job->created_at?->toIso8601String(),
            'updated_at' => $job->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function labourPayload(JobLabourLine $line): array
    {
        return [
            'id' => $line->id,
            'job_id' => $line->job_id,
            'workshop_service_id' => $line->workshop_service_id,
            'service' => $line->relationLoaded('workshopService') ? $line->workshopService : null,
            'description' => $line->description,
            'hours' => $line->hours,
            'rate_minor' => $line->rate_minor,
            'amount_minor' => $line->amount_minor,
            'created_at' => $line->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function partPayload(JobPartLine $line): array
    {
        $line->loadMissing(['product:id,name,sku', 'unit:id,code']);

        return [
            'id' => $line->id,
            'job_id' => $line->job_id,
            'product_id' => $line->product_id,
            'product' => $line->product,
            'unit_id' => $line->unit_id,
            'unit' => $line->unit,
            'quantity' => $line->quantity,
            'unit_price_minor' => $line->unit_price_minor,
            'amount_minor' => $line->amount_minor,
            'stock_movement_id' => $line->stock_movement_id,
            'idempotency_key' => $line->idempotency_key,
            'created_at' => $line->created_at?->toIso8601String(),
        ];
    }
}
