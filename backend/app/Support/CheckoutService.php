<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\Pricing\PriceLine;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CheckoutService
{
    public function __construct(
        private readonly PriceCalculatorFactory $priceCalculatorFactory,
        private readonly StockLedger $stockLedger,
        private readonly DocumentNumberService $documentNumbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function checkout(Branch $branch, User $actor, array $payload, string $idempotencyKey): Sale
    {
        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'A non-empty Idempotency-Key header of at most 100 characters is required.',
            ]);
        }

        $requestHash = $this->requestHash($payload);

        $existing = Sale::where('branch_id', $branch->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            if (! hash_equals($existing->request_hash, $requestHash)) {
                throw new ConflictHttpException('The Idempotency-Key has already been used with a different checkout payload.');
            }

            return $existing->load(['customer', 'items', 'payments', 'receipt']);
        }

        try {
            return DB::transaction(function () use ($branch, $actor, $payload, $idempotencyKey, $requestHash): Sale {
                $existing = Sale::where('branch_id', $branch->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if (! hash_equals($existing->request_hash, $requestHash)) {
                        throw new ConflictHttpException('The Idempotency-Key has already been used with a different checkout payload.');
                    }

                    return $existing->load(['customer', 'items', 'payments', 'receipt']);
                }

                $currencyCode = $branch->currency_code;
                $lines = $this->normalizeLines($payload['lines'] ?? []);
                $payments = $this->normalizePayments($payload['payments'] ?? []);

                if ($lines === []) {
                    throw ValidationException::withMessages([
                        'lines' => 'A checkout must contain at least one line.',
                    ]);
                }

                $priceLines = [];
                $products = [];

                foreach ($lines as $line) {
                    $product = Product::where('branch_id', $branch->id)
                        ->whereKey($line['product_id'])
                        ->first();

                    if (! $product) {
                        throw ValidationException::withMessages([
                            'lines' => 'A product must belong to this branch.',
                        ]);
                    }

                    if (! $product->is_active) {
                        throw ValidationException::withMessages([
                            'lines' => 'Archived products cannot be sold.',
                        ]);
                    }

                    $unitPriceMinor = $this->unitPriceMinor($product, $line['unit_id']);

                    $priceLines[] = new PriceLine(
                        productId: $product->id,
                        unitId: $line['unit_id'],
                        currencyCode: $currencyCode,
                        unitPriceMinor: $unitPriceMinor,
                        quantity: $line['quantity'],
                        floorPriceMinor: $line['floor_price_minor'],
                        discountPercent: $line['discount_percent'],
                        taxRatePercent: $line['tax_rate_percent'],
                    );

                    $products[$product->id] = $product;
                }

                $overrideReason = isset($payload['discount_override_reason']) && is_string($payload['discount_override_reason'])
                    ? trim($payload['discount_override_reason'])
                    : null;

                $calculator = $this->priceCalculatorFactory->make($actor, $overrideReason !== '' ? $overrideReason : null);
                $cart = $calculator->calculateCart($priceLines, $currencyCode);

                if ($cart->requiresApproval && ($overrideReason === null || $overrideReason === '')) {
                    throw ValidationException::withMessages([
                        'discount_override_reason' => 'An approved reason is required for this discount or floor-price override.',
                    ]);
                }

                $paidMinor = 0;

                foreach ($payments as $payment) {
                    if ($payment['currency_code'] !== $currencyCode) {
                        throw ValidationException::withMessages([
                            'payments' => 'All payments must use the branch currency.',
                        ]);
                    }

                    $paidMinor += $payment['amount_minor'];
                }

                if ($paidMinor > $cart->totalMinor) {
                    throw ValidationException::withMessages([
                        'payments' => 'Payment amounts cannot exceed the sale total.',
                    ]);
                }

                $customerId = $payload['customer_id'] ?? null;

                if ($customerId !== null) {
                    $customer = Customer::where('branch_id', $branch->id)
                        ->whereKey($customerId)
                        ->first();

                    if (! $customer) {
                        throw ValidationException::withMessages([
                            'customer_id' => 'The customer must belong to this branch.',
                        ]);
                    }
                }

                $saleNumber = $this->documentNumbers->allocate($branch, 'sale');

                $sale = Sale::create([
                    'branch_id' => $branch->id,
                    'customer_id' => $customerId,
                    'sale_number' => $saleNumber,
                    'status' => 'posted',
                    'payment_status' => $this->paymentStatus($paidMinor, $cart->totalMinor),
                    'currency_code' => $currencyCode,
                    'subtotal_minor' => $cart->subtotalMinor,
                    'discount_minor' => $cart->discountMinor,
                    'tax_minor' => $cart->taxMinor,
                    'total_minor' => $cart->totalMinor,
                    'paid_minor' => $paidMinor,
                    'due_date' => $payload['due_date'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'actor_id' => $actor->id,
                ]);

                foreach ($cart->lines as $index => $line) {
                    $input = $lines[$index];

                    SaleItem::create([
                        'branch_id' => $branch->id,
                        'sale_id' => $sale->id,
                        'product_id' => $line->productId,
                        'product_name' => $products[$line->productId]->name,
                        'sku' => $products[$line->productId]->sku,
                        'unit_id' => $line->unitId,
                        'unit_code' => $this->unitCode($products[$line->productId], $line->unitId),
                        'quantity' => $line->quantity,
                        'unit_price_minor' => $line->unitPriceMinor,
                        'discount_minor' => $line->discountMinor,
                        'tax_minor' => $line->taxMinor,
                        'total_minor' => $line->totalMinor,
                        'currency_code' => $currencyCode,
                    ]);

                    if ($products[$line->productId]->track_stock) {
                        $this->stockLedger->postSale(
                            $branch,
                            $products[$line->productId],
                            $line->quantity,
                            $line->unitId,
                            (string) $sale->id,
                            $actor,
                            'sale:'.$sale->id.':'.$line->productId,
                        );
                    }
                }

                foreach ($payments as $payment) {
                    Payment::create([
                        'branch_id' => $branch->id,
                        'sale_id' => $sale->id,
                        'method' => $payment['method'],
                        'amount_minor' => $payment['amount_minor'],
                        'currency_code' => $payment['currency_code'],
                        'reference' => $payment['reference'] ?? null,
                        'status' => 'recorded',
                        'actor_id' => $actor->id,
                    ]);
                }

                $receiptNumber = $this->documentNumbers->allocate($branch, 'receipt');

                Receipt::create([
                    'branch_id' => $branch->id,
                    'sale_id' => $sale->id,
                    'receipt_number' => $receiptNumber,
                    'issued_at' => now(),
                    'print_count' => 0,
                ]);

                $this->audit->log(
                    'sale.posted',
                    $sale,
                    null,
                    [
                        'sale_number' => $sale->sale_number,
                        'receipt_number' => $receiptNumber,
                        'total_minor' => $sale->total_minor,
                        'payment_status' => $sale->payment_status,
                        'item_count' => count($cart->lines),
                    ],
                    $branch->id,
                    $actor,
                );

                return $sale->load(['customer', 'items.product', 'items.unit', 'payments', 'receipt']);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Sale::where('branch_id', $branch->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new ConflictHttpException('The Idempotency-Key has already been used with a different checkout payload.', $exception);
                }

                return $existing->load(['customer', 'items', 'payments', 'receipt']);
            }

            throw new ConflictHttpException('The checkout conflicted with another concurrent request.', $exception);
        }
    }

    private function paymentStatus(int $paidMinor, int $totalMinor): string
    {
        if ($paidMinor >= $totalMinor) {
            return 'paid';
        }

        if ($paidMinor > 0) {
            return 'partial';
        }

        return 'unpaid';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeLines(mixed $lines): array
    {
        if (! is_array($lines)) {
            return [];
        }

        $normalized = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $normalized[] = [
                'product_id' => (string) ($line['product_id'] ?? ''),
                'unit_id' => (string) ($line['unit_id'] ?? ''),
                'quantity' => (string) ($line['quantity'] ?? ''),
                'discount_percent' => (float) ($line['discount_percent'] ?? 0),
                'floor_price_minor' => (int) ($line['floor_price_minor'] ?? 0),
                'tax_rate_percent' => (float) ($line['tax_rate_percent'] ?? 0),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizePayments(mixed $payments): array
    {
        if (! is_array($payments)) {
            return [];
        }

        $normalized = [];

        foreach ($payments as $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $normalized[] = [
                'method' => (string) ($payment['method'] ?? ''),
                'amount_minor' => (int) ($payment['amount_minor'] ?? 0),
                'currency_code' => (string) ($payment['currency_code'] ?? ''),
                'reference' => isset($payment['reference']) && is_string($payment['reference']) ? trim($payment['reference']) : null,
            ];
        }

        return $normalized;
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

        if (! $productUnit) {
            throw ValidationException::withMessages([
                'lines' => 'The selected unit is not configured for this product.',
            ]);
        }

        return $productUnit->selling_price_minor;
    }

    private function unitCode(Product $product, string $unitId): string
    {
        if ($unitId === $product->base_unit_id) {
            return $product->baseUnit?->code ?? '';
        }

        $productUnit = ProductUnit::where('branch_id', $product->branch_id)
            ->where('product_id', $product->id)
            ->where('unit_id', $unitId)
            ->first();

        return $productUnit?->unit?->code ?? '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
