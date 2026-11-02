<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateReturnRequest;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Support\SaleCollectionService;
use App\Support\SaleReturnService;
use App\Support\SaleVoidService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleVoidService $voids,
        private readonly SaleReturnService $returns,
        private readonly SaleCollectionService $collections,
    ) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);
        $status = $request->query('status');
        $paymentStatus = $request->query('payment_status');

        $sales = $branch->sales()
            ->with(['customer:id,name', 'receipt:id,sale_id,receipt_number'])
            ->when(is_string($status) && $status !== '', fn ($query) => $query->where('status', $status))
            ->when(is_string($paymentStatus) && $paymentStatus !== '', fn ($query) => $query->where('payment_status', $paymentStatus))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $sales->getCollection()->map(fn (Sale $sale): array => $this->salePayload($sale))->all(),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    public function void(Request $request, Branch $branch, Sale $sale): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $sale = $this->voids->void(
            $branch,
            $sale,
            $request->user(),
            (string) $request->input('reason'),
            (string) $request->header('Idempotency-Key', ''),
        );

        return response()->json(['data' => $this->salePayload($sale)]);
    }

    public function collectPayment(Request $request, Branch $branch, Sale $sale): JsonResponse
    {
        $data = $request->validate([
            'payment_account_id' => ['required', 'string'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'in:cash,card,bank_transfer,mobile_money,other'],
            'currency_code' => ['required', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $sale = $this->collections->collect(
            $branch,
            $sale,
            $request->user(),
            $data,
            (string) $request->header('Idempotency-Key', ''),
        );

        return response()->json(['data' => $this->salePayload($sale)]);
    }

    public function receivables(Request $request, Branch $branch): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $sales = $branch->sales()
            ->where('status', '!=', 'voided')
            ->whereColumn('paid_minor', '<', 'total_minor')
            ->with(['customer:id,name'])
            ->orderBy('due_date')
            ->orderBy('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $sales->getCollection()->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'customer' => $sale->customer ? ['id' => $sale->customer->id, 'name' => $sale->customer->name] : null,
                'currency_code' => $sale->currency_code,
                'total_minor' => $sale->total_minor,
                'paid_minor' => $sale->paid_minor,
                'outstanding_minor' => $sale->total_minor - $sale->paid_minor,
                'due_date' => $sale->due_date?->toDateString(),
                'payment_status' => $sale->payment_status,
                'created_at' => $sale->created_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    public function receivablesAgeing(Request $request, Branch $branch): JsonResponse
    {
        $asOf = $request->query('as_of', now()->toDateString());

        $sales = $branch->sales()
            ->where('status', '!=', 'voided')
            ->whereColumn('paid_minor', '<', 'total_minor')
            ->get();

        $buckets = [
            'current' => 0,
            'days_1_30' => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'days_over_90' => 0,
        ];

        foreach ($sales as $sale) {
            $outstanding = $sale->total_minor - $sale->paid_minor;
            $dueDate = $sale->due_date ?? $sale->created_at?->toDateString() ?? $asOf;
            $days = (int) Carbon::parse($asOf)->diffInDays($dueDate, false) * -1;

            if ($days <= 0) {
                $buckets['current'] += $outstanding;
            } elseif ($days <= 30) {
                $buckets['days_1_30'] += $outstanding;
            } elseif ($days <= 60) {
                $buckets['days_31_60'] += $outstanding;
            } elseif ($days <= 90) {
                $buckets['days_61_90'] += $outstanding;
            } else {
                $buckets['days_over_90'] += $outstanding;
            }
        }

        return response()->json([
            'data' => $buckets,
            'as_of' => $asOf,
        ]);
    }

    public function returnsIndex(Request $request, Branch $branch): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $returns = $branch->saleReturns()
            ->with(['sale:id,sale_number', 'items'])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $returns->getCollection()->map(fn (SaleReturn $return): array => $this->returnPayload($return))->all(),
            'meta' => [
                'current_page' => $returns->currentPage(),
                'last_page' => $returns->lastPage(),
                'per_page' => $returns->perPage(),
                'total' => $returns->total(),
            ],
        ]);
    }

    public function storeReturn(CreateReturnRequest $request, Branch $branch, Sale $sale): JsonResponse
    {
        $saleReturn = $this->returns->create(
            $branch,
            $sale,
            $request->user(),
            $request->validated(),
            (string) $request->header('Idempotency-Key', ''),
        );

        return response()->json(['data' => $this->returnPayload($saleReturn)], 201);
    }

    public function showReturn(Branch $branch, SaleReturn $saleReturn): JsonResponse
    {
        $saleReturn->load(['sale:id,sale_number', 'items']);

        return response()->json(['data' => $this->returnPayload($saleReturn)]);
    }

    private function salePayload(Sale $sale): array
    {
        $sale->loadMissing(['customer', 'items', 'payments', 'receipt', 'voidedBy']);

        return [
            'id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'status' => $sale->status,
            'payment_status' => $sale->payment_status,
            'currency_code' => $sale->currency_code,
            'subtotal_minor' => $sale->subtotal_minor,
            'discount_minor' => $sale->discount_minor,
            'tax_minor' => $sale->tax_minor,
            'total_minor' => $sale->total_minor,
            'paid_minor' => $sale->paid_minor,
            'voided_at' => $sale->voided_at?->toIso8601String(),
            'void_reason' => $sale->void_reason,
            'customer' => $sale->customer ? ['id' => $sale->customer->id, 'name' => $sale->customer->name] : null,
            'items' => $sale->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'unit_id' => $item->unit_id,
                'unit_code' => $item->unit_code,
                'quantity' => (string) $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'discount_minor' => $item->discount_minor,
                'tax_minor' => $item->tax_minor,
                'total_minor' => $item->total_minor,
                'currency_code' => $item->currency_code,
            ])->all(),
            'payments' => $sale->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'method' => $payment->method,
                'amount_minor' => $payment->amount_minor,
                'currency_code' => $payment->currency_code,
                'reference' => $payment->reference,
                'status' => $payment->status,
            ])->all(),
            'receipt' => $sale->receipt ? [
                'id' => $sale->receipt->id,
                'receipt_number' => $sale->receipt->receipt_number,
            ] : null,
            'created_at' => $sale->created_at?->toIso8601String(),
        ];
    }

    private function returnPayload(SaleReturn $saleReturn): array
    {
        $saleReturn->loadMissing(['sale:id,sale_number', 'items']);

        return [
            'id' => $saleReturn->id,
            'return_number' => $saleReturn->return_number,
            'sale_id' => $saleReturn->sale_id,
            'sale_number' => $saleReturn->sale?->sale_number,
            'status' => $saleReturn->status,
            'restock_decision' => $saleReturn->restock_decision,
            'refund_status' => $saleReturn->refund_status,
            'reason' => $saleReturn->reason,
            'currency_code' => $saleReturn->currency_code,
            'total_minor' => $saleReturn->total_minor,
            'items' => $saleReturn->items->map(fn ($item) => [
                'id' => $item->id,
                'sale_item_id' => $item->sale_item_id,
                'product_id' => $item->product_id,
                'quantity' => (string) $item->quantity,
                'amount_minor' => $item->amount_minor,
                'currency_code' => $item->currency_code,
            ])->all(),
            'created_at' => $saleReturn->created_at?->toIso8601String(),
        ];
    }
}
