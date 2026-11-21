<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStocktakeRequest;
use App\Models\Branch;
use App\Models\Stocktake;
use App\Models\StocktakeLine;
use App\Support\StocktakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StocktakeController extends Controller
{
    public function __construct(private readonly StocktakeService $stocktakes) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $stocktakes = $branch->stocktakes()
            ->with(['creator:id,name', 'reviewer:id,name', 'approver:id,name'])
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->withCount('lines')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $stocktakes->map(fn (Stocktake $stocktake): array => $this->payload($stocktake))]);
    }

    public function show(Branch $branch, Stocktake $stocktake): JsonResponse
    {
        $this->assertBranch($branch, $stocktake);

        $stocktake->load([
            'branch:id,name,code',
            'creator:id,name',
            'reviewer:id,name',
            'approver:id,name',
            'lines' => fn ($query) => $query->with(['product:id,name,sku', 'unit:id,code', 'stockMovement:id,movement_type,quantity,balance_after'])->orderBy('created_at'),
        ]);

        return response()->json(['data' => $this->payload($stocktake)]);
    }

    public function store(StoreStocktakeRequest $request, Branch $branch): JsonResponse
    {
        $stocktake = $this->stocktakes->create($branch, $request->user(), $request->validated());

        return response()->json(['data' => $this->payload($stocktake)], 201);
    }

    public function review(Request $request, Branch $branch, Stocktake $stocktake): JsonResponse
    {
        $this->assertBranch($branch, $stocktake);
        $stocktake = $this->stocktakes->review($stocktake, $request->user());

        return response()->json(['data' => $this->payload($stocktake->load(['reviewer:id,name', 'lines.product:id,name,sku', 'lines.unit:id,code']))]);
    }

    public function approve(Request $request, Branch $branch, Stocktake $stocktake): JsonResponse
    {
        $this->assertBranch($branch, $stocktake);
        $data = $request->validate(['approval_reference' => ['nullable', 'string', 'max:120']]);
        $stocktake = $this->stocktakes->approve($stocktake, $request->user(), $data);

        return response()->json(['data' => $this->payload($stocktake->load(['approver:id,name', 'lines.stockMovement:id,movement_type,quantity,balance_after']))]);
    }

    private function assertBranch(Branch $branch, Stocktake $stocktake): void
    {
        abort_unless($stocktake->branch_id === $branch->id, 404);
    }

    private function payload(Stocktake $stocktake): array
    {
        return [
            'id' => $stocktake->id,
            'stocktake_number' => $stocktake->stocktake_number,
            'branch_id' => $stocktake->branch_id,
            'branch' => $stocktake->relationLoaded('branch') ? $stocktake->branch : null,
            'reason' => $stocktake->reason,
            'status' => $stocktake->status,
            'created_by' => $stocktake->created_by,
            'creator' => $stocktake->relationLoaded('creator') ? $stocktake->creator : null,
            'reviewed_by' => $stocktake->reviewed_by,
            'reviewer' => $stocktake->relationLoaded('reviewer') ? $stocktake->reviewer : null,
            'reviewed_at' => $stocktake->reviewed_at?->toIso8601String(),
            'approved_by' => $stocktake->approved_by,
            'approver' => $stocktake->relationLoaded('approver') ? $stocktake->approver : null,
            'approved_at' => $stocktake->approved_at?->toIso8601String(),
            'approval_reference' => $stocktake->approval_reference,
            'line_count' => $stocktake->lines_count ?? $stocktake->lines()->count(),
            'lines' => $stocktake->relationLoaded('lines')
                ? $stocktake->lines->map(fn (StocktakeLine $line): array => $this->linePayload($line))->all()
                : [],
            'created_at' => $stocktake->created_at?->toIso8601String(),
            'updated_at' => $stocktake->updated_at?->toIso8601String(),
        ];
    }

    private function linePayload(StocktakeLine $line): array
    {
        return [
            'id' => $line->id,
            'stocktake_id' => $line->stocktake_id,
            'branch_id' => $line->branch_id,
            'product_id' => $line->product_id,
            'product' => $line->relationLoaded('product') ? $line->product : null,
            'unit_id' => $line->unit_id,
            'unit' => $line->relationLoaded('unit') ? $line->unit : null,
            'expected_quantity' => $line->expected_quantity,
            'counted_quantity' => $line->counted_quantity,
            'variance' => $line->variance,
            'reason' => $line->reason,
            'stock_movement_id' => $line->stock_movement_id,
            'stock_movement' => $line->relationLoaded('stockMovement') ? $line->stockMovement : null,
            'created_at' => $line->created_at?->toIso8601String(),
        ];
    }
}
