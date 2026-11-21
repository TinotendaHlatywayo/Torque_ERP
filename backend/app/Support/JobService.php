<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\Customer;
use App\Models\JobLabourLine;
use App\Models\JobPartLine;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\ServiceJob;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkshopService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class JobService
{
    public function __construct(
        private readonly StockLedger $stockLedger,
        private readonly DocumentNumberService $documentNumbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createWorkshopService(Branch $branch, User $actor, array $data): WorkshopService
    {
        $service = $branch->workshopServices()->create([
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => trim((string) $data['name']),
            'description' => $this->nullableString($data['description'] ?? null),
            'default_hours' => (string) $data['default_hours'],
            'default_rate_minor' => (int) $data['default_rate_minor'],
            'currency_code' => strtoupper((string) $data['currency_code']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $this->audit->log('workshop_service.created', $service, null, $this->serviceSnapshot($service), $branch->id, $actor);

        return $service;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateWorkshopService(WorkshopService $service, User $actor, array $data): WorkshopService
    {
        $before = $this->serviceSnapshot($service);
        $changes = [];

        foreach (['name', 'description', 'default_hours', 'default_rate_minor', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $changes[$field] = $field === 'description'
                    ? $this->nullableString($data[$field])
                    : $data[$field];
            }
        }

        $service->update($changes);

        $this->audit->log('workshop_service.updated', $service, $before, $this->serviceSnapshot($service), $service->branch_id, $actor);

        return $service->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createJob(Branch $branch, User $actor, array $data): ServiceJob
    {
        $customer = Customer::where('branch_id', $branch->id)->whereKey($data['customer_id'])->first();

        throw_unless($customer, ValidationException::withMessages([
            'customer_id' => 'The customer must belong to this branch.',
        ]));

        $this->assertBranchTechnician($branch, $data['technician_id'] ?? null);

        $job = DB::transaction(function () use ($branch, $actor, $data): ServiceJob {
            return $branch->jobs()->create([
                'job_number' => $this->documentNumbers->allocate($branch, 'job'),
                'customer_id' => $data['customer_id'],
                'technician_id' => $data['technician_id'] ?? null,
                'vehicle_make' => trim((string) $data['vehicle_make']),
                'vehicle_model' => trim((string) $data['vehicle_model']),
                'vehicle_registration' => $this->nullableString($data['vehicle_registration'] ?? null),
                'vehicle_year' => $this->nullableString($data['vehicle_year'] ?? null),
                'vin' => $this->nullableString($data['vin'] ?? null),
                'complaint' => trim((string) $data['complaint']),
                'status' => ServiceJob::STATUS_DRAFT,
                'created_by' => $actor->id,
            ]);
        });

        $this->audit->log('job.created', $job, null, $this->jobSnapshot($job), $branch->id, $actor);

        return $job->load(['customer', 'technician']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateJob(ServiceJob $job, User $actor, array $data): ServiceJob
    {
        $this->assertStatus($job, [ServiceJob::STATUS_DRAFT, ServiceJob::STATUS_ESTIMATED, ServiceJob::STATUS_IN_PROGRESS], 'update');
        $this->assertBranchTechnician(Branch::findOrFail($job->branch_id), $data['technician_id'] ?? null);

        $before = $this->jobSnapshot($job);
        $changes = [];

        foreach (['technician_id', 'vehicle_make', 'vehicle_model', 'vehicle_registration', 'vehicle_year', 'vin', 'complaint', 'diagnosis'] as $field) {
            if (array_key_exists($field, $data)) {
                $changes[$field] = in_array($field, ['vehicle_registration', 'vehicle_year', 'vin', 'diagnosis'], true)
                    ? $this->nullableString($data[$field])
                    : $data[$field];
            }
        }

        $job->update($changes);
        $this->audit->log('job.updated', $job, $before, $this->jobSnapshot($job), $job->branch_id, $actor);

        return $job->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addLabourLine(ServiceJob $job, User $actor, array $data): JobLabourLine
    {
        $this->assertStatus($job, [ServiceJob::STATUS_DRAFT], 'add labour');

        $service = null;

        if (($data['workshop_service_id'] ?? null) !== null) {
            $service = WorkshopService::where('branch_id', $job->branch_id)
                ->whereKey($data['workshop_service_id'])
                ->first();

            throw_unless($service, ValidationException::withMessages([
                'workshop_service_id' => 'The service must belong to this branch.',
            ]));
        }

        $description = $this->nullableString($data['description'] ?? null) ?? $service?->name;
        $hours = (string) ($data['hours'] ?? $service?->default_hours ?? '');
        $rateMinor = (int) ($data['rate_minor'] ?? $service?->default_rate_minor ?? 0);

        throw_unless($description !== null && $description !== '', ValidationException::withMessages([
            'description' => 'A labour description is required.',
        ]));

        throw_unless(is_numeric($hours) && Decimal::fitsScale($hours, 2) && bccomp($hours, '0', 2) > 0, ValidationException::withMessages([
            'hours' => 'Labour hours must be greater than zero with at most two decimal places.',
        ]));

        $amountMinor = (int) Decimal::roundHalfUp(bcmul($hours, (string) $rateMinor, 4), 0);

        $line = $job->labourLines()->create([
            'branch_id' => $job->branch_id,
            'workshop_service_id' => $service?->id,
            'description' => $description,
            'hours' => $hours,
            'rate_minor' => $rateMinor,
            'amount_minor' => $amountMinor,
        ]);

        $this->audit->log('job.labour_added', $line, null, [
            'job_id' => $job->id,
            'description' => $description,
            'hours' => $hours,
            'rate_minor' => $rateMinor,
            'amount_minor' => $amountMinor,
        ], $job->branch_id, $actor);

        return $line;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addPartLine(ServiceJob $job, User $actor, array $data, ?string $idempotencyKey = null): JobPartLine
    {
        $this->assertStatus($job, [ServiceJob::STATUS_DRAFT], 'add parts');

        if ($idempotencyKey !== null) {
            $existing = $this->findIdempotentLine($job, $idempotencyKey, $this->requestHash($data));

            if ($existing) {
                return $existing;
            }
        }

        $product = Product::where('branch_id', $job->branch_id)->whereKey($data['product_id'])->first();

        throw_unless($product, ValidationException::withMessages([
            'product_id' => 'The product must belong to this branch.',
        ]));

        throw_unless($product->is_active && $product->track_stock, ValidationException::withMessages([
            'product_id' => 'Only active products that track stock can be consumed on a job.',
        ]));

        $unit = Unit::where('branch_id', $job->branch_id)->whereKey($data['unit_id'])->first();

        throw_unless($unit && $unit->is_active, ValidationException::withMessages([
            'unit_id' => 'The unit must be an active unit of this branch.',
        ]));

        $quantity = (string) $data['quantity'];

        throw_unless(is_numeric($quantity) && Decimal::fitsScale($quantity, 4) && bccomp($quantity, '0', 4) > 0, ValidationException::withMessages([
            'quantity' => 'The quantity must be greater than zero with at most four decimal places.',
        ]));

        $unitPriceMinor = (int) ($data['unit_price_minor'] ?? $this->unitPriceMinor($product, $unit->id));
        $amountMinor = (int) Decimal::roundHalfUp(bcmul($quantity, (string) $unitPriceMinor, 4), 0);
        $requestHash = $idempotencyKey !== null ? $this->requestHash($data) : null;

        if ($idempotencyKey !== null) {
            $existing = $this->findIdempotentLine($job, $idempotencyKey, $requestHash);

            if ($existing) {
                return $existing;
            }
        }

        try {
            return DB::transaction(function () use (
                $job,
                $actor,
                $product,
                $unit,
                $quantity,
                $unitPriceMinor,
                $amountMinor,
                $idempotencyKey,
                $requestHash,
            ): JobPartLine {
                if ($idempotencyKey !== null) {
                    $existing = $this->findIdempotentLine($job, $idempotencyKey, $requestHash);

                    if ($existing) {
                        return $existing;
                    }
                }

                $line = $job->partLines()->create([
                    'branch_id' => $job->branch_id,
                    'product_id' => $product->id,
                    'unit_id' => $unit->id,
                    'quantity' => $quantity,
                    'unit_price_minor' => $unitPriceMinor,
                    'amount_minor' => $amountMinor,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                ]);

                $posting = $this->stockLedger->postJobConsumption(
                    Branch::findOrFail($job->branch_id),
                    $product,
                    $quantity,
                    $unit->id,
                    (string) $line->id,
                    $actor,
                    'job-part:'.$line->id,
                    'Job '.$job->job_number.' part consumption',
                );

                $line->update(['stock_movement_id' => $posting->movement->id]);

                $this->audit->log('job.part_consumed', $line, null, [
                    'job_id' => $job->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'stock_movement_id' => $posting->movement->id,
                    'replayed' => $posting->replayed,
                ], $job->branch_id, $actor);

                return $line->refresh();
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if ($idempotencyKey !== null) {
                $existing = $this->findIdempotentLine($job, $idempotencyKey, $requestHash);

                if ($existing) {
                    return $existing;
                }
            }

            throw new ConflictHttpException('The job part line conflicted with another concurrent request.', $exception);
        }
    }

    public function estimate(ServiceJob $job, User $actor): ServiceJob
    {
        $this->assertStatus($job, [ServiceJob::STATUS_DRAFT], 'estimate');

        $labourLines = $job->labourLines()->count();
        $partLines = $job->partLines()->count();

        throw_unless($labourLines + $partLines > 0, ValidationException::withMessages([
            'lines' => 'An estimate requires at least one labour or part line.',
        ]));

        $estimateMinor = (int) $job->labourLines()->sum('amount_minor') + (int) $job->partLines()->sum('amount_minor');
        $before = $this->jobSnapshot($job);

        $job->update([
            'status' => ServiceJob::STATUS_ESTIMATED,
            'estimate_minor' => $estimateMinor,
        ]);

        $this->audit->log('job.estimated', $job, $before, $this->jobSnapshot($job), $job->branch_id, $actor);

        return $job->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function approve(ServiceJob $job, User $actor, array $data): ServiceJob
    {
        $this->assertStatus($job, [ServiceJob::STATUS_ESTIMATED], 'approve');

        $before = $this->jobSnapshot($job);
        $job->update([
            'status' => ServiceJob::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $actor->id,
            'approval_reference' => trim((string) $data['approval_reference']),
            'approval_channel' => $this->nullableString($data['approval_channel'] ?? null),
        ]);

        $this->audit->log('job.customer_approved', $job, $before, $this->jobSnapshot($job), $job->branch_id, $actor);

        return $job->refresh();
    }

    public function start(ServiceJob $job, User $actor): ServiceJob
    {
        $this->assertStatus($job, [ServiceJob::STATUS_APPROVED], 'start');

        throw_unless($job->approved_at !== null, ValidationException::withMessages([
            'approval' => 'Customer approval is required before work can start.',
        ]));

        throw_unless($job->technician_id !== null, ValidationException::withMessages([
            'technician_id' => 'A technician must be assigned before work can start.',
        ]));

        $before = $this->jobSnapshot($job);
        $job->update(['status' => ServiceJob::STATUS_IN_PROGRESS]);
        $this->audit->log('job.started', $job, $before, $this->jobSnapshot($job), $job->branch_id, $actor);

        return $job->refresh();
    }

    public function complete(ServiceJob $job, User $actor): ServiceJob
    {
        $this->assertStatus($job, [ServiceJob::STATUS_IN_PROGRESS], 'complete');

        throw_unless($this->nullableString($job->diagnosis) !== null, ValidationException::withMessages([
            'diagnosis' => 'A diagnosis is required before a job can be completed.',
        ]));

        $before = $this->jobSnapshot($job);
        $job->update([
            'status' => ServiceJob::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $this->audit->log('job.completed', $job, $before, $this->jobSnapshot($job), $job->branch_id, $actor);

        return $job->refresh();
    }

    public function cancel(ServiceJob $job, User $actor, ?string $reason): ServiceJob
    {
        $this->assertStatus($job, [ServiceJob::STATUS_DRAFT, ServiceJob::STATUS_ESTIMATED, ServiceJob::STATUS_APPROVED, ServiceJob::STATUS_IN_PROGRESS], 'cancel');

        $before = $this->jobSnapshot($job);
        $job->update([
            'status' => ServiceJob::STATUS_CANCELLED,
            'previous_status' => $job->status,
            'cancelled_at' => now(),
            'cancellation_reason' => $this->nullableString($reason),
        ]);

        $this->audit->log('job.cancelled', $job, $before, [
            ...$this->jobSnapshot($job),
            'stock_reversed' => false,
            'invoice_reversed' => false,
        ], $job->branch_id, $actor);

        return $job->refresh();
    }

    public function reopen(ServiceJob $job, User $actor): ServiceJob
    {
        $this->assertStatus($job, [ServiceJob::STATUS_CANCELLED], 'reopen');

        $before = $this->jobSnapshot($job);
        $job->update([
            'status' => $job->previous_status ?? ServiceJob::STATUS_DRAFT,
            'previous_status' => null,
            'cancelled_at' => null,
        ]);

        $this->audit->log('job.reopened', $job, $before, [
            ...$this->jobSnapshot($job),
            'stock_reversed' => false,
            'invoice_reversed' => false,
        ], $job->branch_id, $actor);

        return $job->refresh();
    }

    public function linkInvoice(ServiceJob $job, User $actor, string $saleId): ServiceJob
    {
        $this->assertStatus($job, [ServiceJob::STATUS_APPROVED, ServiceJob::STATUS_IN_PROGRESS, ServiceJob::STATUS_COMPLETED], 'link an invoice');

        $sale = Sale::where('branch_id', $job->branch_id)->whereKey($saleId)->first();

        throw_unless($sale, ValidationException::withMessages([
            'sale_id' => 'The invoice must belong to this branch.',
        ]));

        if ($job->customer_id !== null && $sale->customer_id !== null && $job->customer_id !== $sale->customer_id) {
            throw ValidationException::withMessages([
                'sale_id' => 'The invoice does not belong to the job customer.',
            ]);
        }

        if ($job->sale_id === $sale->id) {
            return $job;
        }

        $alreadyLinked = ServiceJob::where('sale_id', $sale->id)->whereKeyNot($job->id)->exists();

        throw_unless(! $alreadyLinked, new ConflictHttpException('This invoice is already linked to another job.'));

        $before = $this->jobSnapshot($job);
        $job->update(['sale_id' => $sale->id]);
        $this->audit->log('job.invoice_linked', $job, $before, [
            ...$this->jobSnapshot($job),
            'invoice_generated' => false,
        ], $job->branch_id, $actor);

        return $job->refresh();
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function assertStatus(ServiceJob $job, array $allowed, string $action): void
    {
        throw_unless(in_array($job->status, $allowed, true), ValidationException::withMessages([
            'status' => "The job cannot {$action} while it is {$job->status}.",
        ]));
    }

    private function assertBranchTechnician(Branch $branch, ?string $userId): void
    {
        if ($userId === null) {
            return;
        }

        $exists = BranchUser::where('branch_id', $branch->id)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->exists();

        throw_unless($exists, ValidationException::withMessages([
            'technician_id' => 'The technician must be an active member of this branch.',
        ]));
    }

    private function unitPriceMinor(Product $product, string $unitId): int
    {
        if ($unitId === $product->base_unit_id) {
            return $product->selling_price_minor;
        }

        $productUnit = ProductUnit::where('branch_id', $product->branch_id)
            ->where('product_id', $product->id)
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->first();

        throw_unless($productUnit, ValidationException::withMessages([
            'unit_id' => 'The selected unit is not configured for this product.',
        ]));

        return $productUnit->selling_price_minor;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findIdempotentLine(ServiceJob $job, string $key, ?string $requestHash): ?JobPartLine
    {
        $line = $job->partLines()->where('idempotency_key', $key)->first();

        if (! $line) {
            return null;
        }

        if ($requestHash !== null && $line->request_hash !== null && ! hash_equals($line->request_hash, $requestHash)) {
            throw new ConflictHttpException('The Idempotency-Key has already been used with a different job part payload.');
        }

        $line->idempotentReplay = true;

        return $line->loadMissing(['product', 'unit', 'stockMovement']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requestHash(array $data): string
    {
        ksort($data);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceSnapshot(WorkshopService $service): array
    {
        return [
            'code' => $service->code,
            'name' => $service->name,
            'description' => $service->description,
            'default_hours' => $service->default_hours,
            'default_rate_minor' => $service->default_rate_minor,
            'currency_code' => $service->currency_code,
            'is_active' => $service->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jobSnapshot(ServiceJob $job): array
    {
        return [
            'job_number' => $job->job_number,
            'customer_id' => $job->customer_id,
            'technician_id' => $job->technician_id,
            'vehicle_make' => $job->vehicle_make,
            'vehicle_model' => $job->vehicle_model,
            'complaint' => $job->complaint,
            'diagnosis' => $job->diagnosis,
            'status' => $job->status,
            'estimate_minor' => $job->estimate_minor,
            'approved_at' => $job->approved_at?->toIso8601String(),
            'approval_reference' => $job->approval_reference,
            'completed_at' => $job->completed_at?->toIso8601String(),
            'sale_id' => $job->sale_id,
        ];
    }
}
