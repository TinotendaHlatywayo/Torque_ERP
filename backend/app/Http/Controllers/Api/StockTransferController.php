<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockTransferRequest;
use App\Models\Branch;
use App\Models\StockTransfer;
use App\Support\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function __construct(private readonly StockTransferService $transfers) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $transfers = $branch->stockTransfers()
            ->with([
                'destinationBranch:id,name,code',
                'product:id,name,sku',
                'unit:id,code,name',
                'destinationProduct:id,name,sku',
                'destinationUnit:id,code,name',
            ])
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $transfers->map(fn (StockTransfer $transfer): array => $this->payload($transfer))]);
    }

    public function show(Branch $branch, StockTransfer $stockTransfer): JsonResponse
    {
        $this->assertBranch($branch, $stockTransfer);

        $stockTransfer->load([
            'sourceBranch:id,name,code',
            'destinationBranch:id,name,code',
            'product:id,name,sku',
            'unit:id,code,name',
            'destinationProduct:id,name,sku',
            'destinationUnit:id,code,name',
            'creator:id,name',
            'dispatcher:id,name',
            'inTransitUser:id,name',
            'receiver:id,name',
            'canceller:id,name',
            'sourceMovement',
            'destinationMovement',
            'cancellationMovement',
        ]);

        return response()->json(['data' => $this->payload($stockTransfer)]);
    }

    public function store(StoreStockTransferRequest $request, Branch $branch): JsonResponse
    {
        $stockTransfer = $this->transfers->create($branch, $request->user(), $request->validated());

        return response()->json(['data' => $this->payload($stockTransfer)], 201);
    }

    public function dispatch(Request $request, Branch $branch, StockTransfer $stockTransfer): JsonResponse
    {
        $this->assertBranch($branch, $stockTransfer);
        $stockTransfer = $this->transfers->dispatch($stockTransfer, $request->user());

        return response()->json(['data' => $this->payload($stockTransfer)]);
    }

    public function inTransit(Request $request, Branch $branch, StockTransfer $stockTransfer): JsonResponse
    {
        $this->assertBranch($branch, $stockTransfer);
        $stockTransfer = $this->transfers->markInTransit($stockTransfer, $request->user());

        return response()->json(['data' => $this->payload($stockTransfer)]);
    }

    public function receive(Request $request, Branch $branch, StockTransfer $stockTransfer): JsonResponse
    {
        $this->assertBranch($branch, $stockTransfer);
        $stockTransfer = $this->transfers->receive($stockTransfer, $request->user());

        return response()->json(['data' => $this->payload($stockTransfer)]);
    }

    public function cancel(Request $request, Branch $branch, StockTransfer $stockTransfer): JsonResponse
    {
        $this->assertBranch($branch, $stockTransfer);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $stockTransfer = $this->transfers->cancel($stockTransfer, $request->user(), $data['reason']);

        return response()->json(['data' => $this->payload($stockTransfer)]);
    }

    private function assertBranch(Branch $branch, StockTransfer $stockTransfer): void
    {
        abort_unless($stockTransfer->source_branch_id === $branch->id, 404);
    }

    private function payload(StockTransfer $transfer): array
    {
        return [
            'id' => $transfer->id,
            'transfer_number' => $transfer->transfer_number,
            'source_branch_id' => $transfer->source_branch_id,
            'source_branch' => $transfer->relationLoaded('sourceBranch') ? $transfer->sourceBranch : null,
            'destination_branch_id' => $transfer->destination_branch_id,
            'destination_branch' => $transfer->relationLoaded('destinationBranch') ? $transfer->destinationBranch : null,
            'product_id' => $transfer->product_id,
            'product' => $transfer->relationLoaded('product') ? $transfer->product : null,
            'unit_id' => $transfer->unit_id,
            'unit' => $transfer->relationLoaded('unit') ? $transfer->unit : null,
            'destination_product_id' => $transfer->destination_product_id,
            'destination_product' => $transfer->relationLoaded('destinationProduct') ? $transfer->destinationProduct : null,
            'destination_unit_id' => $transfer->destination_unit_id,
            'destination_unit' => $transfer->relationLoaded('destinationUnit') ? $transfer->destinationUnit : null,
            'quantity' => $transfer->quantity,
            'dispatched_quantity' => $transfer->dispatched_quantity,
            'reason' => $transfer->reason,
            'status' => $transfer->status,
            'created_by' => $transfer->created_by,
            'creator' => $transfer->relationLoaded('creator') ? $transfer->creator : null,
            'dispatched_by' => $transfer->dispatched_by,
            'dispatcher' => $transfer->relationLoaded('dispatcher') ? $transfer->dispatcher : null,
            'dispatched_at' => $transfer->dispatched_at?->toIso8601String(),
            'in_transit_by' => $transfer->in_transit_by,
            'in_transit_user' => $transfer->relationLoaded('inTransitUser') ? $transfer->inTransitUser : null,
            'in_transit_at' => $transfer->in_transit_at?->toIso8601String(),
            'received_by' => $transfer->received_by,
            'receiver' => $transfer->relationLoaded('receiver') ? $transfer->receiver : null,
            'received_at' => $transfer->received_at?->toIso8601String(),
            'cancelled_by' => $transfer->cancelled_by,
            'canceller' => $transfer->relationLoaded('canceller') ? $transfer->canceller : null,
            'cancelled_at' => $transfer->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $transfer->cancellation_reason,
            'source_movement_id' => $transfer->source_movement_id,
            'destination_movement_id' => $transfer->destination_movement_id,
            'cancellation_movement_id' => $transfer->cancellation_movement_id,
            'source_movement' => $transfer->relationLoaded('sourceMovement') ? $transfer->sourceMovement : null,
            'destination_movement' => $transfer->relationLoaded('destinationMovement') ? $transfer->destinationMovement : null,
            'cancellation_movement' => $transfer->relationLoaded('cancellationMovement') ? $transfer->cancellationMovement : null,
            'created_at' => $transfer->created_at?->toIso8601String(),
            'updated_at' => $transfer->updated_at?->toIso8601String(),
        ];
    }
}
