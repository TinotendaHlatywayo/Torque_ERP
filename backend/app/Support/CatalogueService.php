<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\CatalogueImportLog;
use App\Models\Product;
use App\Models\ProductFitment;
use App\Models\ProductOemRef;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CatalogueService
{
    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function search(Branch $branch, array $params): array
    {
        $q = isset($params['q']) ? trim((string) $params['q']) : '';
        $make = isset($params['make']) ? trim((string) $params['make']) : '';
        $model = isset($params['model']) ? trim((string) $params['model']) : '';
        $year = isset($params['year']) ? trim((string) $params['year']) : '';
        $engine = isset($params['engine']) ? trim((string) $params['engine']) : '';
        $oem = isset($params['oem']) ? trim((string) $params['oem']) : '';

        $results = collect();

        // Exact SKU/barcode (highest priority, always reliable)
        if ($q !== '') {
            $exact = Product::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->where(function ($query) use ($q): void {
                    $query->whereRaw('UPPER(sku) = ?', [Str::upper($q)])
                        ->orWhere('barcode', $q);
                })
                ->with(['baseUnit:id,code,name', 'category:id,name'])
                ->get();

            foreach ($exact as $p) {
                $results->push([
                    'product' => $p,
                    'match_type' => 'exact_sku_barcode',
                    'match_detail' => 'Exact SKU or barcode match',
                    'source' => 'product_catalogue',
                    'fitment' => null,
                    'oem' => null,
                ]);
            }
        }

        // OEM search (exact then typo-tolerant on token level not needed; match contains/equals)
        if ($oem !== '') {
            $oemRows = ProductOemRef::query()
                ->where('branch_id', $branch->id)
                ->where(function ($query) use ($oem): void {
                    $query->whereRaw('UPPER(oem_number) = ?', [Str::upper($oem)]);
                    // allow simple prefix for partial
                })
                ->with(['product' => fn ($q) => $q->where('is_active', true)->with(['baseUnit:id,code,name', 'category:id,name'])])
                ->limit(200)
                ->get();

            foreach ($oemRows as $ref) {
                if (! $ref->product) {
                    continue;
                }
                $results->push([
                    'product' => $ref->product,
                    'match_type' => 'oem',
                    'match_detail' => 'OEM ref: '.$ref->oem_number.($ref->brand ? ' ('.$ref->brand.')' : ''),
                    'source' => $ref->source.' / '.$ref->source_ref.' ('.$ref->verified.')',
                    'fitment' => null,
                    'oem' => [
                        'oem_number' => $ref->oem_number,
                        'brand' => $ref->brand,
                        'source' => $ref->source,
                        'source_ref' => $ref->source_ref,
                        'verified' => $ref->verified,
                    ],
                ]);
            }
        }

        // Fitment search: make/model/year/engine
        if ($make !== '' || $model !== '' || $year !== '' || $engine !== '' || ($q !== '' && $oem === '' && $results->isEmpty())) {
            $fitments = ProductFitment::query()
                ->where('branch_id', $branch->id)
                ->when($make !== '', function ($query) use ($make): void {
                    $query->where(function ($q2) use ($make): void {
                        $q2->whereRaw('UPPER(make) = ?', [Str::upper($make)]);
                        if (strlen($make) >= 5) {
                            // allow typo distance 1 on make
                        }
                    });
                })
                ->when($model !== '', function ($query) use ($model): void {
                    $query->where(function ($q2) use ($model): void {
                        $q2->whereRaw('UPPER(model) = ?', [Str::upper($model)]);
                    });
                })
                ->when($engine !== '', fn ($query) => $query->whereRaw('UPPER(engine) LIKE ?', ['%'.Str::upper($engine).'%']))
                ->with(['product' => fn ($q) => $q->where('is_active', true)->with(['baseUnit:id,code,name', 'category:id,name'])])
                ->limit(300)
                ->get();

            foreach ($fitments as $fit) {
                if (! $fit->product) {
                    continue;
                }
                // year filter if provided
                if ($year !== '') {
                    $yf = $fit->year_from ? (int) preg_replace('/\D+/', '', (string) $fit->year_from) : null;
                    $yt = $fit->year_to ? (int) preg_replace('/\D+/', '', (string) $fit->year_to) : null;
                    $yi = (int) preg_replace('/\D+/', '', $year);
                    if ($yf !== null && $yi < $yf) {
                        continue;
                    }
                    if ($yt !== null && $yi > $yt) {
                        continue;
                    }
                }
                // apply deterministic typo tolerance for make/model tokens length>=5
                if ($make !== '' && strlen($make) >= 5 && ! $this->matchesWithTypo($make, $fit->make)) {
                    continue;
                }
                if ($model !== '' && strlen($model) >= 5 && ! $this->matchesWithTypo($model, $fit->model)) {
                    continue;
                }
                $results->push([
                    'product' => $fit->product,
                    'match_type' => 'fitment',
                    'match_detail' => trim(($fit->make.' '.$fit->model.' '.$fit->year_from.'-'.$fit->year_to.' '.$fit->engine)),
                    'source' => $fit->source.' / '.$fit->source_ref.' ('.$fit->verified.')',
                    'fitment' => [
                        'make' => $fit->make,
                        'model' => $fit->model,
                        'year_from' => $fit->year_from,
                        'year_to' => $fit->year_to,
                        'engine' => $fit->engine,
                        'source' => $fit->source,
                        'source_ref' => $fit->source_ref,
                        'verified' => $fit->verified,
                    ],
                    'oem' => null,
                ]);
            }
        }

        // If q provided and still empty, try typo-tolerant search on product name/sku prefix? Keep conservative.
        if ($q !== '' && $results->isEmpty() && strlen($q) >= 5) {
            $products = Product::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->limit(200)
                ->get();
            foreach ($products as $p) {
                if ($this->matchesWithTypo($q, $p->sku) || $this->matchesWithTypo($q, $p->name)) {
                    $results->push([
                        'product' => $p,
                        'match_type' => 'typo_tolerant',
                        'match_detail' => 'Approximate match (typo tolerance, Levenshtein<=1)',
                        'source' => 'product_catalogue',
                        'fitment' => null,
                        'oem' => null,
                    ]);
                }
            }
        }

        // dedupe by product_id + match_type+source
        $dedup = [];
        $final = [];
        foreach ($results as $r) {
            $key = $r['product']->id.'|'.$r['match_type'].'|'.$r['source'];
            if (isset($dedup[$key])) {
                continue;
            }
            $dedup[$key] = true;
            $p = $r['product'];
            $final[] = [
                'product_id' => $p->id,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'name' => $p->name,
                'selling_price_minor' => $p->selling_price_minor,
                'currency_code' => $p->currency_code,
                'match_type' => $r['match_type'],
                'match_detail' => $r['match_detail'],
                'source' => $r['source'],
                'fitment' => $r['fitment'],
                'oem' => $r['oem'],
            ];
        }

        return [
            'data' => array_values($final),
            'meta' => [
                'count' => count($final),
                'query' => $params,
            ],
        ];
    }

    public function matchesWithTypo(string $needle, string $haystack): bool
    {
        $a = Str::upper(trim($needle));
        $b = Str::upper(trim($haystack));
        if ($a === $b) {
            return true;
        }
        if (strlen($a) < 5 || strlen($b) < 5) {
            return false;
        }
        // only allow small strings to avoid cost; and threshold 1
        if (abs(strlen($a) - strlen($b)) > 1) {
            // still may be distance 1? no, insertion/deletion gives diff; but allow if close
        }
        $dist = levenshtein($a, $b);

        return $dist <= 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function importFitments(Branch $branch, array $rows, string $source, string $sourceRef, string $format, string $idempotencyKey, ?int $userId): array
    {
        if ($format !== 'json') {
            throw ValidationException::withMessages(['format' => 'Only json format is supported.']);
        }
        $existing = CatalogueImportLog::query()
            ->where('branch_id', $branch->id)
            ->where('type', 'fitment')
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing) {
            return [
                'idempotent_replay' => true,
                'import_log_id' => $existing->id,
                'rows_received' => $existing->rows_received,
                'rows_created' => $existing->rows_created,
                'rows_skipped' => $existing->rows_skipped,
                'summary' => $existing->summary,
            ];
        }

        $created = 0;
        $skipped = 0;
        $batchId = (string) Str::ulid();

        DB::transaction(function () use ($branch, $rows, $source, $sourceRef, $format, $idempotencyKey, $userId, &$created, &$skipped, $batchId): void {
            foreach ($rows as $row) {
                $productId = $row['product_id'] ?? null;
                if (! $productId || ! Product::where('branch_id', $branch->id)->where('id', $productId)->exists()) {
                    $skipped++;

                    continue;
                }
                $make = trim((string) ($row['make'] ?? ''));
                $model = trim((string) ($row['model'] ?? ''));
                if ($make === '' || $model === '') {
                    $skipped++;

                    continue;
                }
                try {
                    ProductFitment::create([
                        'branch_id' => $branch->id,
                        'product_id' => $productId,
                        'make' => $make,
                        'model' => $model,
                        'year_from' => $row['year_from'] ?? null,
                        'year_to' => $row['year_to'] ?? null,
                        'engine' => $row['engine'] ?? null,
                        'source' => $source,
                        'source_ref' => $sourceRef,
                        'verified' => 'declared',
                        'notes' => $row['notes'] ?? null,
                        'import_batch_id' => $batchId,
                        'created_by' => $userId,
                    ]);
                    $created++;
                } catch (\Throwable $e) {
                    $skipped++;
                }
            }
            CatalogueImportLog::create([
                'branch_id' => $branch->id,
                'type' => 'fitment',
                'source' => $source,
                'format' => $format,
                'idempotency_key' => $idempotencyKey,
                'rows_received' => count($rows),
                'rows_created' => $created,
                'rows_skipped' => $skipped,
                'summary' => ['batch_id' => $batchId],
                'created_by' => $userId,
            ]);
        });

        return [
            'idempotent_replay' => false,
            'rows_received' => count($rows),
            'rows_created' => $created,
            'rows_skipped' => $skipped,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function importOemRefs(Branch $branch, array $rows, string $source, string $sourceRef, string $format, string $idempotencyKey, ?int $userId): array
    {
        if ($format !== 'json') {
            throw ValidationException::withMessages(['format' => 'Only json format is supported.']);
        }
        $existing = CatalogueImportLog::query()
            ->where('branch_id', $branch->id)
            ->where('type', 'oem')
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing) {
            return [
                'idempotent_replay' => true,
                'import_log_id' => $existing->id,
                'rows_received' => $existing->rows_received,
                'rows_created' => $existing->rows_created,
                'rows_skipped' => $existing->rows_skipped,
                'summary' => $existing->summary,
            ];
        }

        $created = 0;
        $skipped = 0;
        $batchId = (string) Str::ulid();

        DB::transaction(function () use ($branch, $rows, $source, $sourceRef, $format, $idempotencyKey, $userId, &$created, &$skipped, $batchId): void {
            foreach ($rows as $row) {
                $productId = $row['product_id'] ?? null;
                if (! $productId || ! Product::where('branch_id', $branch->id)->where('id', $productId)->exists()) {
                    $skipped++;

                    continue;
                }
                $oemNumber = trim((string) ($row['oem_number'] ?? ''));
                if ($oemNumber === '') {
                    $skipped++;

                    continue;
                }
                try {
                    ProductOemRef::create([
                        'branch_id' => $branch->id,
                        'product_id' => $productId,
                        'oem_number' => $oemNumber,
                        'brand' => $row['brand'] ?? null,
                        'source' => $source,
                        'source_ref' => $sourceRef,
                        'verified' => 'declared',
                        'notes' => $row['notes'] ?? null,
                        'import_batch_id' => $batchId,
                        'created_by' => $userId,
                    ]);
                    $created++;
                } catch (\Throwable $e) {
                    $skipped++;
                }
            }
            CatalogueImportLog::create([
                'branch_id' => $branch->id,
                'type' => 'oem',
                'source' => $source,
                'format' => $format,
                'idempotency_key' => $idempotencyKey,
                'rows_received' => count($rows),
                'rows_created' => $created,
                'rows_skipped' => $skipped,
                'summary' => ['batch_id' => $batchId],
                'created_by' => $userId,
            ]);
        });

        return [
            'idempotent_replay' => false,
            'rows_received' => count($rows),
            'rows_created' => $created,
            'rows_skipped' => $skipped,
        ];
    }
}
