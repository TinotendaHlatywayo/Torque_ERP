<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoyaltyDiscountQuoteRequest;
use App\Http\Requests\RecordLoyaltySaleEventRequest;
use App\Http\Requests\UpdateLoyaltyConfigRequest;
use App\Models\Branch;
use App\Models\LoyaltyQualification;
use App\Models\LoyaltySaleEvent;
use App\Support\AuditLogger;
use App\Support\LoyaltyConfigService;
use App\Support\LoyaltyQualificationService;
use App\Support\LoyaltyStackingService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function __construct(
        private readonly LoyaltyConfigService $configs,
        private readonly LoyaltyQualificationService $qualifications,
        private readonly LoyaltyStackingService $stacking,
        private readonly AuditLogger $audit,
    ) {}

    public function config(Branch $branch): JsonResponse
    {
        return response()->json(['data' => $this->configs->payload($this->configs->forBranch($branch))]);
    }

    public function updateConfig(UpdateLoyaltyConfigRequest $request, Branch $branch): JsonResponse
    {
        $config = $this->configs->update($branch, $request->validated(), $request->user());

        return response()->json(['data' => $this->configs->payload($config)]);
    }

    public function qualifications(Branch $branch): JsonResponse
    {
        return response()->json([
            'data' => $this->qualifications->qualifications($branch)
                ->map(fn (LoyaltyQualification $qualification): array => $this->qualificationPayload($qualification))
                ->all(),
        ]);
    }

    public function recalculate(Request $request, Branch $branch): JsonResponse
    {
        return response()->json(['data' => $this->qualifications->recalculate($branch, $request->user())]);
    }

    public function recordSaleEvent(RecordLoyaltySaleEventRequest $request, Branch $branch): JsonResponse
    {
        $event = $this->qualifications->recordSaleEvent($branch, $request->validated());

        return response()->json(['data' => $this->saleEventPayload($event)], 201);
    }

    public function winbackCandidates(Branch $branch): JsonResponse
    {
        return response()->json(['data' => $this->qualifications->winbackCandidates($branch)]);
    }

    public function winbackExport(Request $request, Branch $branch): JsonResponse
    {
        $export = $this->qualifications->winbackExportStub($branch);

        $this->audit->log(
            'loyalty.winback.export',
            'loyalty_winback_export',
            null,
            ['candidate_count' => count($export['candidates']), 'delivery_status' => $export['delivery_status']],
            $branch->id,
            $request->user(),
        );

        return response()->json(['data' => $export]);
    }

    public function discountQuote(LoyaltyDiscountQuoteRequest $request, Branch $branch): JsonResponse
    {
        $branchUser = $request->attributes->get('branch_user');
        $canApprove = $branchUser?->hasPermission(Permissions::BRANCH_LOYALTY_APPROVE) ?? false;

        return response()->json([
            'data' => $this->stacking->quote($branch, $request->user(), $canApprove, $request->validated()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function qualificationPayload(LoyaltyQualification $qualification): array
    {
        return [
            'id' => $qualification->id,
            'customer_id' => $qualification->customer_id,
            'customer_name' => $qualification->customer?->name,
            'currency_code' => $qualification->currency_code,
            'qualifying_sale_count' => $qualification->qualifying_sale_count,
            'gross_sales_minor' => $qualification->gross_sales_minor,
            'returned_minor' => $qualification->returned_minor,
            'net_qualifying_minor' => $qualification->net_qualifying_minor,
            'last_purchase_at' => $qualification->last_purchase_at?->toIso8601String(),
            'qualifies' => $qualification->qualifies,
            'is_inactive' => $qualification->is_inactive,
            'marketing_channel' => $qualification->customer?->marketing_channel,
            'calculated_at' => $qualification->calculated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function saleEventPayload(LoyaltySaleEvent $event): array
    {
        return [
            'id' => $event->id,
            'customer_id' => $event->customer_id,
            'sale_id' => $event->sale_id,
            'event_key' => $event->event_key,
            'source' => $event->source,
            'source_ref' => $event->source_ref,
            'total_minor' => $event->total_minor,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
        ];
    }
}
