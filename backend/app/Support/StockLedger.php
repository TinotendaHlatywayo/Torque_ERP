<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StockLedger
{
    public function __construct(
        private readonly ProductUnitConverter $converter,
        private readonly AuditLogger $audit,
    ) {}

    public function postOpening(
        Branch $branch,
        Product $product,
        string $quantity,
        string $unitId,
        ?string $reason,
        User $actor,
        string $idempotencyKey,
    ): StockPostingResult {
        $unitQuantity = $this->normalizeInputQuantity($quantity);

        if (bccomp($unitQuantity, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Opening stock must be greater than zero.',
            ]);
        }

        [$baseQuantity, $unit, $factor] = $this->convertToBase($product, $unitId, $unitQuantity);

        return $this->persist(
            branch: $branch,
            product: $product,
            movementType: StockMovement::TYPE_OPENING,
            source: StockMovement::SOURCE_OPENING,
            sourceId: null,
            reason: $this->reasonOrDefault($reason, 'Opening stock'),
            actor: $actor,
            idempotencyKey: $idempotencyKey,
            baseQuantity: $baseQuantity,
            unitQuantity: $unitQuantity,
            conversionFactor: $factor,
            unit: $unit,
            reverses: null,
            openingMarker: StockMovement::OPENING_MARKER,
        );
    }

    public function postCorrection(
        Branch $branch,
        Product $product,
        string $quantity,
        string $unitId,
        string $reason,
        User $actor,
        string $idempotencyKey,
    ): StockPostingResult {
        $unitQuantity = $this->normalizeInputQuantity($quantity);

        if (bccomp($unitQuantity, '0', 4) === 0) {
            throw ValidationException::withMessages([
                'quantity' => 'A correction must change stock by a non-zero quantity.',
            ]);
        }

        [$baseQuantity, $unit, $factor] = $this->convertToBase($product, $unitId, $unitQuantity);

        return $this->persist(
            branch: $branch,
            product: $product,
            movementType: StockMovement::TYPE_CORRECTION,
            source: StockMovement::SOURCE_MANUAL,
            sourceId: null,
            reason: $this->requiredReason($reason),
            actor: $actor,
            idempotencyKey: $idempotencyKey,
            baseQuantity: $baseQuantity,
            unitQuantity: $unitQuantity,
            conversionFactor: $factor,
            unit: $unit,
            reverses: null,
            openingMarker: null,
        );
    }

    public function postSale(
        Branch $branch,
        Product $product,
        string $quantity,
        string $unitId,
        string $sourceId,
        User $actor,
        string $idempotencyKey,
        ?string $reason = null,
    ): StockPostingResult {
        $unitQuantity = $this->normalizeInputQuantity($quantity);

        if (bccomp($unitQuantity, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'A sale quantity must be greater than zero.',
            ]);
        }

        [$baseQuantity, $unit, $factor] = $this->convertToBase($product, $unitId, $unitQuantity);

        return $this->persist(
            branch: $branch,
            product: $product,
            movementType: StockMovement::TYPE_SALE,
            source: StockMovement::SOURCE_SALE,
            sourceId: $sourceId,
            reason: $this->reasonOrDefault($reason, 'Sale'),
            actor: $actor,
            idempotencyKey: $idempotencyKey,
            baseQuantity: bcmul($baseQuantity, '-1', 4),
            unitQuantity: bcmul($unitQuantity, '-1', 4),
            conversionFactor: $factor,
            unit: $unit,
            reverses: null,
            openingMarker: null,
        );
    }

    public function postJobConsumption(
        Branch $branch,
        Product $product,
        string $quantity,
        string $unitId,
        string $sourceId,
        User $actor,
        string $idempotencyKey,
        ?string $reason = null,
    ): StockPostingResult {
        $unitQuantity = $this->normalizeInputQuantity($quantity);

        if (bccomp($unitQuantity, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'A job consumption quantity must be greater than zero.',
            ]);
        }

        [$baseQuantity, $unit, $factor] = $this->convertToBase($product, $unitId, $unitQuantity);

        return $this->persist(
            branch: $branch,
            product: $product,
            movementType: StockMovement::TYPE_JOB_CONSUMPTION,
            source: StockMovement::SOURCE_JOB,
            sourceId: $sourceId,
            reason: $this->reasonOrDefault($reason, 'Job part consumption'),
            actor: $actor,
            idempotencyKey: $idempotencyKey,
            baseQuantity: bcmul($baseQuantity, '-1', 4),
            unitQuantity: bcmul($unitQuantity, '-1', 4),
            conversionFactor: $factor,
            unit: $unit,
            reverses: null,
            openingMarker: null,
        );
    }

    public function postTransferDispatch(
        Branch $branch,
        Product $product,
        string $quantity,
        string $unitId,
        string $transferId,
        string $reason,
        User $actor,
        string $idempotencyKey,
    ): StockPostingResult {
        $unitQuantity = $this->normalizeInputQuantity($quantity);

        if (bccomp($unitQuantity, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'A transfer dispatch quantity must be greater than zero.',
            ]);
        }

        [$baseQuantity, $unit, $factor] = $this->convertToBase($product, $unitId, $unitQuantity);

        return $this->persist(
            branch: $branch,
            product: $product,
            movementType: StockMovement::TYPE_TRANSFER_OUT,
            source: StockMovement::SOURCE_STOCK_TRANSFER,
            sourceId: $transferId,
            reason: $this->requiredReason($reason),
            actor: $actor,
            idempotencyKey: $idempotencyKey,
            baseQuantity: bcmul($baseQuantity, '-1', 4),
            unitQuantity: bcmul($unitQuantity, '-1', 4),
            conversionFactor: $factor,
            unit: $unit,
            reverses: null,
            openingMarker: null,
        );
    }

    public function postTransferReceipt(
        Branch $branch,
        Product $product,
        string $quantity,
        string $unitId,
        string $transferId,
        string $reason,
        User $actor,
        string $idempotencyKey,
    ): StockPostingResult {
        $unitQuantity = $this->normalizeInputQuantity($quantity);

        if (bccomp($unitQuantity, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'A transfer receipt quantity must be greater than zero.',
            ]);
        }

        [$baseQuantity, $unit, $factor] = $this->convertToBase($product, $unitId, $unitQuantity);

        return $this->persist(
            branch: $branch,
            product: $product,
            movementType: StockMovement::TYPE_TRANSFER_IN,
            source: StockMovement::SOURCE_STOCK_TRANSFER,
            sourceId: $transferId,
            reason: $this->requiredReason($reason),
            actor: $actor,
            idempotencyKey: $idempotencyKey,
            baseQuantity: $baseQuantity,
            unitQuantity: $unitQuantity,
            conversionFactor: $factor,
            unit: $unit,
            reverses: null,
            openingMarker: null,
        );
    }

    public function postStocktakeAdjustment(
        Branch $branch,
        Product $product,
        string $quantity,
        string $unitId,
        string $stocktakeLineId,
        string $reason,
        User $actor,
        string $idempotencyKey,
    ): StockPostingResult {
        $unitQuantity = $this->normalizeInputQuantity($quantity);

        if (bccomp($unitQuantity, '0', 4) === 0) {
            throw ValidationException::withMessages([
                'quantity' => 'A stocktake adjustment must change stock by a non-zero quantity.',
            ]);
        }

        [$baseQuantity, $unit, $factor] = $this->convertToBase($product, $unitId, $unitQuantity);

        return $this->persist(
            branch: $branch,
            product: $product,
            movementType: StockMovement::TYPE_CORRECTION,
            source: StockMovement::SOURCE_STOCKTAKE,
            sourceId: $stocktakeLineId,
            reason: $this->requiredReason($reason),
            actor: $actor,
            idempotencyKey: $idempotencyKey,
            baseQuantity: $baseQuantity,
            unitQuantity: $unitQuantity,
            conversionFactor: $factor,
            unit: $unit,
            reverses: null,
            openingMarker: null,
        );
    }

    public function reverse(
        Branch $branch,
        StockMovement $movement,
        string $reason,
        User $actor,
        string $idempotencyKey,
    ): StockPostingResult {
        if ($movement->branch_id !== $branch->id) {
            throw new NotFoundHttpException;
        }

        if ($movement->movement_type === StockMovement::TYPE_REVERSAL) {
            throw new ConflictHttpException('A reversal movement cannot itself be reversed.');
        }

        $movement->loadMissing(['product', 'unit']);

        if (! $movement->product || ! $movement->unit) {
            throw new ConflictHttpException('The original movement is no longer available for reversal.');
        }

        return $this->persist(
            branch: $branch,
            product: $movement->product,
            movementType: StockMovement::TYPE_REVERSAL,
            source: StockMovement::SOURCE_REVERSAL,
            sourceId: (string) $movement->id,
            reason: $this->requiredReason($reason),
            actor: $actor,
            idempotencyKey: $idempotencyKey,
            baseQuantity: bcmul((string) $movement->quantity, '-1', 4),
            unitQuantity: bcmul((string) $movement->unit_quantity, '-1', 4),
            conversionFactor: (string) $movement->unit_conversion_factor,
            unit: $movement->unit,
            reverses: $movement,
            openingMarker: null,
        );
    }

    private function persist(
        Branch $branch,
        Product $product,
        string $movementType,
        string $source,
        ?string $sourceId,
        string $reason,
        User $actor,
        string $idempotencyKey,
        string $baseQuantity,
        string $unitQuantity,
        string $conversionFactor,
        Unit $unit,
        ?StockMovement $reverses,
        ?string $openingMarker,
    ): StockPostingResult {
        $idempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);
        $sourceId = $sourceId !== null ? trim($sourceId) : null;
        $requestHash = $this->requestHash([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'movement_type' => $movementType,
            'source' => $source,
            'source_id' => $sourceId,
            'reason' => $reason,
            'quantity' => $baseQuantity,
            'unit_quantity' => $unitQuantity,
            'unit_conversion_factor' => $conversionFactor,
            'reverses_movement_id' => $reverses?->id,
        ]);

        $existing = $this->findByIdempotencyKey($branch, $idempotencyKey);

        if ($existing) {
            return $this->replay($existing, $requestHash);
        }

        try {
            return DB::transaction(function () use (
                $branch,
                $product,
                $movementType,
                $source,
                $sourceId,
                $reason,
                $actor,
                $idempotencyKey,
                $baseQuantity,
                $unitQuantity,
                $conversionFactor,
                $unit,
                $reverses,
                $openingMarker,
                $requestHash,
            ): StockPostingResult {
                $currentProduct = Product::where('branch_id', $branch->id)
                    ->whereKey($product->id)
                    ->firstOrFail();

                $level = StockLevel::where('branch_id', $branch->id)
                    ->where('product_id', $currentProduct->id)
                    ->lockForUpdate()
                    ->first();

                if (! $level) {
                    throw ValidationException::withMessages([
                        'product_id' => 'The product does not have a stock balance row.',
                    ]);
                }

                $existing = StockMovement::where('branch_id', $branch->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $this->replay($existing, $requestHash);
                }

                $this->guardProductPosting($currentProduct, $movementType);

                if ($openingMarker !== null) {
                    $openingExists = StockMovement::where('branch_id', $branch->id)
                        ->where('product_id', $currentProduct->id)
                        ->whereNotNull('opening_marker')
                        ->lockForUpdate()
                        ->first();

                    if ($openingExists) {
                        throw new ConflictHttpException('Opening stock has already been posted for this product; use a correction.');
                    }
                }

                if ($reverses) {
                    $alreadyReversed = StockMovement::where('reverses_movement_id', $reverses->id)
                        ->lockForUpdate()
                        ->first();

                    if ($alreadyReversed) {
                        throw new ConflictHttpException('This stock movement has already been reversed.');
                    }
                }

                $beforeQuantity = (string) $level->quantity_on_hand;
                $balanceAfter = bcadd($beforeQuantity, $baseQuantity, 4);

                if (bccomp($balanceAfter, '0', 4) < 0) {
                    throw new ConflictHttpException('Insufficient stock: the posting would make quantity on hand negative.');
                }

                if (bccomp($balanceAfter, (string) $level->quantity_reserved, 4) < 0) {
                    throw new ConflictHttpException('Insufficient available stock: some units are held for customer reservations.');
                }

                $movement = StockMovement::create([
                    'branch_id' => $branch->id,
                    'product_id' => $currentProduct->id,
                    'unit_id' => $unit->id,
                    'movement_type' => $movementType,
                    'source' => $source,
                    'source_id' => $sourceId,
                    'quantity' => $baseQuantity,
                    'unit_quantity' => $unitQuantity,
                    'unit_conversion_factor' => $conversionFactor,
                    'balance_after' => $balanceAfter,
                    'reason' => $reason,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'opening_marker' => $openingMarker,
                    'reverses_movement_id' => $reverses?->id,
                    'actor_id' => $actor->id,
                ]);

                $level->update(['quantity_on_hand' => $balanceAfter]);

                $this->audit->log(
                    'stock.'.$movementType.'.posted',
                    $movement,
                    ['quantity_on_hand' => $beforeQuantity],
                    [
                        'movement_id' => $movement->id,
                        'product_id' => $currentProduct->id,
                        'quantity' => $baseQuantity,
                        'balance_after' => $balanceAfter,
                        'reason' => $reason,
                    ],
                    $branch->id,
                    $actor,
                );

                return new StockPostingResult($movement->refresh(), false);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->findByIdempotencyKey($branch, $idempotencyKey);

            if ($existing) {
                return $this->replay($existing, $requestHash);
            }

            throw new ConflictHttpException('The stock posting conflicted with another concurrent request.', $exception);
        }
    }

    private function guardProductPosting(Product $product, string $movementType): void
    {
        if ($movementType === StockMovement::TYPE_REVERSAL) {
            return;
        }

        if (! $product->track_stock) {
            throw ValidationException::withMessages([
                'product_id' => 'Stock movements can only be posted for products that track stock.',
            ]);
        }

        if (! $product->is_active && in_array($movementType, [StockMovement::TYPE_OPENING, StockMovement::TYPE_SALE, StockMovement::TYPE_JOB_CONSUMPTION], true)) {
            throw ValidationException::withMessages([
                'product_id' => 'Stock cannot be opened, sold or consumed for an archived product.',
            ]);
        }
    }

    /**
     * @return array{0: string, 1: Unit, 2: string}
     */
    private function convertToBase(Product $product, string $unitId, string $unitQuantity): array
    {
        $conversion = $this->converter->convert(
            $product->loadMissing('baseUnit'),
            $unitId,
            $product->base_unit_id,
            $unitQuantity,
        );

        if (! Decimal::fitsScale($conversion['quantity'], 4)) {
            throw ValidationException::withMessages([
                'quantity' => 'The converted quantity cannot be represented at four decimal places.',
            ]);
        }

        return [
            Decimal::trim($conversion['quantity']),
            $conversion['from_unit'],
            $conversion['from_factor'],
        ];
    }

    private function normalizeInputQuantity(string $quantity): string
    {
        if (! is_numeric($quantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'The quantity must be numeric.',
            ]);
        }

        if (! Decimal::fitsScale($quantity, 4)) {
            throw ValidationException::withMessages([
                'quantity' => 'The quantity may have at most four decimal places.',
            ]);
        }

        return Decimal::trim(bcadd($quantity, '0', 4));
    }

    private function normalizeIdempotencyKey(string $key): string
    {
        $key = trim($key);

        if ($key === '' || strlen($key) > 100) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'A non-empty Idempotency-Key header of at most 100 characters is required.',
            ]);
        }

        return $key;
    }

    private function requiredReason(string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for this stock movement.',
            ]);
        }

        return $reason;
    }

    private function reasonOrDefault(?string $reason, string $default): string
    {
        $reason = trim((string) $reason);

        return $reason !== '' ? $reason : $default;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function findByIdempotencyKey(Branch $branch, string $idempotencyKey): ?StockMovement
    {
        return StockMovement::where('branch_id', $branch->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function replay(StockMovement $movement, string $requestHash): StockPostingResult
    {
        if (! hash_equals($movement->request_hash, $requestHash)) {
            throw new ConflictHttpException('The Idempotency-Key has already been used with a different stock posting payload.');
        }

        return new StockPostingResult($movement, true);
    }
}
