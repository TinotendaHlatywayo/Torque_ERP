<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\LoyaltyQualification;
use App\Models\LoyaltySaleEvent;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class LoyaltyQualificationService
{
    private const RETURN_STATUSES_EXCLUDED = ['cancelled', 'rejected'];

    public function __construct(
        private readonly LoyaltyConfigService $configs,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordSaleEvent(Branch $branch, array $payload): LoyaltySaleEvent
    {
        $customer = Customer::where('branch_id', $branch->id)
            ->whereKey((string) $payload['customer_id'])
            ->firstOrFail();

        $occurredAt = Carbon::parse((string) $payload['occurred_at'], 'UTC');
        $sourceRef = isset($payload['source_ref']) && is_string($payload['source_ref']) && trim($payload['source_ref']) !== ''
            ? trim($payload['source_ref'])
            : null;
        $totalMinor = (int) $payload['total_minor'];
        $eventKey = trim((string) $payload['event_key']);
        $source = isset($payload['source']) && is_string($payload['source']) && $payload['source'] !== ''
            ? $payload['source']
            : 'import';
        $dedupeHash = $this->dedupeHash($branch->id, $customer->id, $sourceRef, $totalMinor, $occurredAt);

        return DB::transaction(function () use ($branch, $customer, $payload, $occurredAt, $sourceRef, $totalMinor, $eventKey, $source, $dedupeHash): LoyaltySaleEvent {
            $byKey = LoyaltySaleEvent::where('branch_id', $branch->id)
                ->where('event_key', $eventKey)
                ->lockForUpdate()
                ->first();

            if ($byKey) {
                if (! hash_equals($byKey->dedupe_hash, $dedupeHash)) {
                    throw new ConflictHttpException('The loyalty sale event key has already been used with a different payload.');
                }

                return $byKey;
            }

            $byHash = LoyaltySaleEvent::where('branch_id', $branch->id)
                ->where('dedupe_hash', $dedupeHash)
                ->lockForUpdate()
                ->first();

            if ($byHash) {
                return $byHash;
            }

            return LoyaltySaleEvent::create([
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'sale_id' => isset($payload['sale_id']) && is_string($payload['sale_id']) ? $payload['sale_id'] : null,
                'event_key' => $eventKey,
                'dedupe_hash' => $dedupeHash,
                'source' => $source,
                'source_ref' => $sourceRef,
                'total_minor' => $totalMinor,
                'occurred_at' => $occurredAt,
            ]);
        }, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function recalculate(Branch $branch, User $actor): array
    {
        $config = $this->configs->forBranch($branch);
        $this->ingestCommittedSales($branch);

        return DB::transaction(function () use ($branch, $actor, $config): array {
            $events = LoyaltySaleEvent::where('branch_id', $branch->id)->get();
            $saleIds = $events->whereNotNull('sale_id')->pluck('sale_id')->filter()->unique()->all();

            $returnsBySale = SaleReturn::where('branch_id', $branch->id)
                ->whereIn('sale_id', $saleIds)
                ->whereNotIn('status', self::RETURN_STATUSES_EXCLUDED)
                ->get(['sale_id', 'total_minor'])
                ->groupBy('sale_id')
                ->map(fn ($rows): int => (int) $rows->sum('total_minor'));

            $inactiveBefore = now()->subDays($config->inactivity_days);
            $calculatedAt = now();
            $processed = 0;

            foreach ($events->groupBy('customer_id') as $customerId => $customerEvents) {
                $grossMinor = (int) $customerEvents->sum('total_minor');
                $returnedMinor = 0;

                foreach ($customerEvents as $event) {
                    if ($event->sale_id !== null) {
                        $returnedMinor += (int) ($returnsBySale->get($event->sale_id, 0));
                    }
                }

                $netMinor = max(0, $grossMinor - $returnedMinor);
                $lastPurchaseAt = $customerEvents->max('occurred_at');
                $qualifies = $customerEvents->count() >= $config->min_qualifying_sales
                    && $netMinor >= $config->min_qualifying_minor;
                $isInactive = $lastPurchaseAt !== null
                    && Carbon::parse((string) $lastPurchaseAt)->lessThanOrEqualTo($inactiveBefore);

                LoyaltyQualification::updateOrCreate(
                    ['branch_id' => $branch->id, 'customer_id' => (string) $customerId],
                    [
                        'currency_code' => $branch->currency_code,
                        'qualifying_sale_count' => $customerEvents->count(),
                        'gross_sales_minor' => $grossMinor,
                        'returned_minor' => $returnedMinor,
                        'net_qualifying_minor' => $netMinor,
                        'last_purchase_at' => $lastPurchaseAt,
                        'qualifies' => $qualifies,
                        'is_inactive' => $isInactive,
                        'calculated_at' => $calculatedAt,
                    ],
                );

                $processed++;
            }

            $this->audit->log(
                'loyalty.recalculated',
                'loyalty_qualification',
                null,
                [
                    'customers_processed' => $processed,
                    'sale_events' => $events->count(),
                    'inactivity_days' => $config->inactivity_days,
                    'calculated_at' => $calculatedAt->toIso8601String(),
                ],
                $branch->id,
                $actor,
            );

            return [
                'customers_processed' => $processed,
                'sale_events' => $events->count(),
                'inactivity_days' => $config->inactivity_days,
                'calculated_at' => $calculatedAt->toIso8601String(),
            ];
        });
    }

    public function ingestCommittedSales(Branch $branch): int
    {
        $ingested = 0;

        Sale::where('branch_id', $branch->id)
            ->where('status', 'posted')
            ->whereNull('voided_at')
            ->whereNotNull('customer_id')
            ->orderBy('created_at')
            ->each(function (Sale $sale) use ($branch, &$ingested): void {
                $this->recordSaleEvent($branch, [
                    'customer_id' => $sale->customer_id,
                    'sale_id' => $sale->id,
                    'event_key' => 'sale:'.$sale->id,
                    'source' => 'sale',
                    'source_ref' => $sale->sale_number,
                    'total_minor' => $sale->total_minor,
                    'occurred_at' => $sale->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ]);

                $ingested++;
            });

        return $ingested;
    }

    /**
     * @return Collection<int, LoyaltyQualification>
     */
    public function qualifications(Branch $branch): Collection
    {
        return LoyaltyQualification::where('branch_id', $branch->id)
            ->with('customer:id,name,marketing_channel')
            ->orderByDesc('net_qualifying_minor')
            ->limit(100)
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function winbackCandidates(Branch $branch): array
    {
        $config = $this->configs->forBranch($branch);
        $inactiveBefore = now()->subDays($config->inactivity_days);
        $customersById = Customer::where('branch_id', $branch->id)
            ->get(['id', 'name', 'marketing_channel', 'marketing_consent_at', 'marketing_channel_active'])
            ->keyBy('id');

        return LoyaltyQualification::where('branch_id', $branch->id)
            ->where('qualifies', true)
            ->orderBy('last_purchase_at')
            ->get()
            ->filter(function (LoyaltyQualification $qualification) use ($customersById, $inactiveBefore): bool {
                $customer = $customersById->get($qualification->customer_id);

                return $customer instanceof Customer
                    && $qualification->last_purchase_at !== null
                    && Carbon::parse((string) $qualification->last_purchase_at)->lessThanOrEqualTo($inactiveBefore)
                    && $customer->hasActiveMarketingChannel();
            })
            ->map(function (LoyaltyQualification $qualification) use ($customersById): array {
                $customer = $customersById->get($qualification->customer_id);

                return [
                    'customer_id' => $qualification->customer_id,
                    'name' => $customer?->name,
                    'marketing_channel' => $customer?->marketing_channel,
                    'last_purchase_at' => $qualification->last_purchase_at?->toIso8601String(),
                    'days_inactive' => (int) Carbon::parse((string) $qualification->last_purchase_at)->diffInDays(now()),
                    'net_qualifying_minor' => $qualification->net_qualifying_minor,
                    'qualifying_sale_count' => $qualification->qualifying_sale_count,
                    'currency_code' => $qualification->currency_code,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function winbackExportStub(Branch $branch): array
    {
        $candidates = $this->winbackCandidates($branch);

        return [
            'delivery_status' => 'NOT_RUN',
            'channel_integration' => 'not_configured',
            'reason' => 'Win-back outreach requires explicit consent, an active channel and a configured delivery integration. No automated message was sent.',
            'contact_disposition' => 'REDACTED_PENDING_CHANNEL_INTEGRATION',
            'generated_at' => now()->toIso8601String(),
            'candidates' => array_map(static fn (array $candidate): array => [
                'customer_id' => $candidate['customer_id'],
                'marketing_channel' => $candidate['marketing_channel'],
                'days_inactive' => $candidate['days_inactive'],
            ], $candidates),
        ];
    }

    private function dedupeHash(string $branchId, string $customerId, ?string $sourceRef, int $totalMinor, Carbon $occurredAt): string
    {
        return hash('sha256', implode('|', [
            $branchId,
            $customerId,
            $sourceRef ?? '',
            (string) $totalMinor,
            $occurredAt->copy()->utc()->format('Y-m-d H:i:s'),
        ]));
    }
}
