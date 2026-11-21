<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\Branch;
use App\Support\AssetService;
use App\Support\DepreciationEstimateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(
        private readonly AssetService $assets,
        private readonly DepreciationEstimateService $depreciationEstimates,
    ) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $assets = $branch->assets()
            ->with('actor:id,name')
            ->withCount('maintenances')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->orderByDesc('acquisition_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $assets->getCollection()->map(fn (Asset $asset): array => $this->payload($asset))->all(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    public function store(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'acquisition_date' => ['required', 'date'],
            'cost_minor' => ['required', 'integer', 'min:1'],
            'currency_code' => ['required', 'string', 'size:3'],
            'location' => ['required', 'string', 'max:180'],
            'custodian' => ['required', 'string', 'max:180'],
            'source_type' => ['nullable', 'string', 'in:expense,supplier_bill,money_movement', 'required_with:source_id'],
            'source_id' => ['nullable', 'string', 'max:36', 'required_with:source_type'],
        ]);

        $asset = $this->assets->acquire($branch, $request->user(), $data);

        return response()->json(['data' => $this->payload($asset)], 201);
    }

    public function show(Branch $branch, Asset $asset): JsonResponse
    {
        $this->assertBranch($branch, $asset);

        $asset->load([
            'actor:id,name',
            'maintenances' => fn ($query) => $query->with(['expense:id,description', 'payment:id,reference', 'actor:id,name'])->orderByDesc('performed_date'),
        ]);

        return response()->json(['data' => $this->payload($asset)]);
    }

    public function depreciationEstimate(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'as_of' => ['nullable', 'date'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
            'residual_minor' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:active,disposed'],
        ]);

        $asOf = $data['as_of'] ?? now()->toDateString();
        $usefulLifeYears = (int) $data['useful_life_years'];
        $residualMinor = (int) ($data['residual_minor'] ?? 0);

        $estimates = $branch->assets()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->orderBy('name')
            ->get()
            ->map(fn (Asset $asset): array => $this->depreciationEstimates->estimate($asset, $asOf, $usefulLifeYears, $residualMinor))
            ->all();

        return response()->json([
            'data' => $estimates,
            'meta' => [
                'as_of' => $asOf,
                'label' => DepreciationEstimateService::LABEL,
                'accounting_use' => DepreciationEstimateService::ACCOUNTING_USE,
                'policy_status' => 'unconfirmed',
                'notice' => DepreciationEstimateService::NOTICE,
                'posted' => false,
                'count' => count($estimates),
            ],
        ]);
    }

    public function assetDepreciationEstimate(Request $request, Branch $branch, Asset $asset): JsonResponse
    {
        $this->assertBranch($branch, $asset);

        $data = $request->validate([
            'as_of' => ['nullable', 'date'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
            'residual_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        $estimate = $this->depreciationEstimates->estimate(
            $asset,
            $data['as_of'] ?? now()->toDateString(),
            (int) $data['useful_life_years'],
            (int) ($data['residual_minor'] ?? 0),
        );

        return response()->json(['data' => $estimate]);
    }

    public function storeMaintenance(Request $request, Branch $branch, Asset $asset): JsonResponse
    {
        $this->assertBranch($branch, $asset);

        $data = $request->validate([
            'performed_date' => ['required', 'date'],
            'cost_minor' => ['required', 'integer', 'min:1'],
            'currency_code' => ['required', 'string', 'size:3'],
            'provider' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:500'],
            'expense_id' => ['nullable', 'string', 'max:36'],
            'payment_id' => ['nullable', 'string', 'max:36'],
        ]);

        $maintenance = $this->assets->recordMaintenance($branch, $asset, $request->user(), $data);

        return response()->json(['data' => $this->maintenancePayload($maintenance)], 201);
    }

    public function dispose(Request $request, Branch $branch, Asset $asset): JsonResponse
    {
        $this->assertBranch($branch, $asset);

        $data = $request->validate([
            'disposal_date' => ['required', 'date'],
            'proceeds_minor' => ['required', 'integer', 'min:0'],
            'accumulated_estimate_minor' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->assets->dispose($branch, $asset, $request->user(), $data);

        return response()->json([
            'data' => [
                'asset' => $this->payload($result['asset']->load('actor:id,name')),
                'financial_effect' => $result['financial_effect'],
            ],
        ]);
    }

    private function assertBranch(Branch $branch, Asset $asset): void
    {
        abort_unless($asset->branch_id === $branch->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'description' => $asset->description,
            'acquisition_date' => $asset->acquisition_date->toDateString(),
            'cost_minor' => $asset->cost_minor,
            'currency_code' => $asset->currency_code,
            'location' => $asset->location,
            'custodian' => $asset->custodian,
            'status' => $asset->status,
            'source_type' => $asset->source_type,
            'source_id' => $asset->source_id,
            'disposal_date' => $asset->disposal_date?->toDateString(),
            'disposal_proceeds_minor' => $asset->disposal_proceeds_minor,
            'disposal_notes' => $asset->disposal_notes,
            'actor' => $asset->relationLoaded('actor') ? $asset->actor : null,
            'maintenance_count' => $asset->maintenances_count ?? ($asset->relationLoaded('maintenances') ? $asset->maintenances->count() : null),
            'maintenances' => $asset->relationLoaded('maintenances')
                ? $asset->maintenances->map(fn (AssetMaintenance $maintenance): array => $this->maintenancePayload($maintenance))->all()
                : [],
            'created_at' => $asset->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function maintenancePayload(AssetMaintenance $maintenance): array
    {
        return [
            'id' => $maintenance->id,
            'asset_id' => $maintenance->asset_id,
            'performed_date' => $maintenance->performed_date->toDateString(),
            'cost_minor' => $maintenance->cost_minor,
            'currency_code' => $maintenance->currency_code,
            'provider' => $maintenance->provider,
            'description' => $maintenance->description,
            'expense_id' => $maintenance->expense_id,
            'payment_id' => $maintenance->payment_id,
            'expense' => $maintenance->relationLoaded('expense') ? $maintenance->expense : null,
            'payment' => $maintenance->relationLoaded('payment') ? $maintenance->payment : null,
            'actor' => $maintenance->relationLoaded('actor') ? $maintenance->actor : null,
            'created_at' => $maintenance->created_at?->toIso8601String(),
        ];
    }
}
