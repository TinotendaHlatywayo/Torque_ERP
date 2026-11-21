<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReverseStockMovementRequest;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\StockLedger;
use App\Support\StockPostingResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StockMovementController extends Controller
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $filters = Validator::make($request->query(), [
            'product_id' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'in:opening,sale,correction,reversal'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);

        $movements = $branch->stockMovements()
            ->with([
                'product:id,sku,name,base_unit_id,track_stock,is_active',
                'unit:id,code,name,symbol,allow_decimal,is_active',
                'actor:id,name',
                'reverses:id,movement_type,source_id',
            ])
            ->withExists('reversedBy as is_reversed')
            ->when(isset($filters['product_id']), fn ($query) => $query->where('product_id', $filters['product_id']))
            ->when(isset($filters['type']), fn ($query) => $query->where('movement_type', $filters['type']))
            ->when(isset($filters['from']), fn ($query) => $query->where('created_at', '>=', $filters['from'].' 00:00:00'))
            ->when(isset($filters['to']), fn ($query) => $query->where('created_at', '<=', $filters['to'].' 23:59:59'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $movements->getCollection()
                ->map(fn (StockMovement $movement): array => $this->payload($movement)),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    public function store(StoreStockMovementRequest $request, Branch $branch, Product $product): JsonResponse
    {
        $data = $request->validated();

        $result = $data['type'] === StockMovement::TYPE_OPENING
            ? $this->ledger->postOpening(
                $branch,
                $product,
                (string) $data['quantity'],
                (string) $data['unit_id'],
                $data['reason'] ?? null,
                $request->user(),
                (string) $data['idempotency_key'],
            )
            : $this->ledger->postCorrection(
                $branch,
                $product,
                (string) $data['quantity'],
                (string) $data['unit_id'],
                (string) $data['reason'],
                $request->user(),
                (string) $data['idempotency_key'],
            );

        return $this->movementResponse($result);
    }

    public function reverse(ReverseStockMovementRequest $request, Branch $branch, StockMovement $stockMovement): JsonResponse
    {
        $data = $request->validated();

        $result = $this->ledger->reverse(
            $branch,
            $stockMovement,
            (string) $data['reason'],
            $request->user(),
            (string) $data['idempotency_key'],
        );

        return $this->movementResponse($result);
    }

    private function movementResponse(StockPostingResult $result): JsonResponse
    {
        $movement = $result->movement->load([
            'product:id,sku,name,base_unit_id,track_stock,is_active',
            'unit:id,code,name,symbol,allow_decimal,is_active',
            'actor:id,name',
            'reverses:id,movement_type,source_id',
        ]);

        $response = response()->json(
            ['data' => $this->payload($movement)],
            $result->replayed ? 200 : 201,
        );

        if ($result->replayed) {
            $response->header('Idempotency-Replayed', 'true');
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'movement_type' => $movement->movement_type,
            'source' => $movement->source,
            'source_id' => $movement->source_id,
            'quantity' => (string) $movement->quantity,
            'unit_quantity' => (string) $movement->unit_quantity,
            'unit_conversion_factor' => (string) $movement->unit_conversion_factor,
            'balance_after' => (string) $movement->balance_after,
            'reason' => $movement->reason,
            'product' => $movement->product ? [
                'id' => $movement->product->id,
                'sku' => $movement->product->sku,
                'name' => $movement->product->name,
                'track_stock' => $movement->product->track_stock,
                'is_active' => $movement->product->is_active,
            ] : null,
            'unit' => $movement->unit ? [
                'id' => $movement->unit->id,
                'code' => $movement->unit->code,
                'name' => $movement->unit->name,
                'symbol' => $movement->unit->symbol,
                'allow_decimal' => $movement->unit->allow_decimal,
            ] : null,
            'actor' => $movement->actor ? [
                'id' => $movement->actor->id,
                'name' => $movement->actor->name,
            ] : null,
            'reverses_movement_id' => $movement->reverses_movement_id,
            'reverses' => $movement->relationLoaded('reverses') && $movement->reverses ? [
                'id' => $movement->reverses->id,
                'movement_type' => $movement->reverses->movement_type,
                'source_id' => $movement->reverses->source_id,
            ] : null,
            'is_reversed' => (bool) ($movement->is_reversed ?? $movement->reversedBy()->exists()),
            'created_at' => $movement->created_at?->toIso8601String(),
        ];
    }
}
