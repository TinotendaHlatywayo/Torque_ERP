<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Branch;
use App\Models\CmsMedia;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\AuditLogger;
use App\Support\CmsImageProcessor;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProductController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $status = in_array($request->query('status'), ['active', 'inactive', 'all'], true)
            ? $request->query('status')
            : 'active';

        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);
        $search = trim((string) $request->query('q', ''));
        $categoryId = $request->query('category_id');
        $sort = (string) $request->query('sort', 'name');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $query = $branch->products()
            ->with(['category:id,name,is_active', 'baseUnit:id,code,name', 'stockLevel', 'publicImage'])
            ->withCount('units')
            ->when($status === 'active', fn ($builder) => $builder->where('is_active', true))
            ->when($status === 'inactive', fn ($builder) => $builder->where('is_active', false))
            ->when(is_string($categoryId) && $categoryId !== '', fn ($builder) => $builder->where('category_id', $categoryId))
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('sku', 'like', $search.'%')
                        ->orWhere('name', 'like', $search.'%')
                        ->orWhere('barcode', $search);
                });
            });

        $column = match ($sort) {
            'sku' => 'sku',
            'price' => 'selling_price_minor',
            'updated_at' => 'updated_at',
            default => 'name',
        };

        $products = $query
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => $products->getCollection()
                ->map(fn (Product $product): array => $this->payload($product)),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(Branch $branch, Product $product): JsonResponse
    {
        $product->load(['category:id,name,is_active', 'baseUnit:id,code,name,symbol,allow_decimal', 'stockLevel', 'publicImage', 'units.unit']);
        $product->loadCount('units');

        return response()->json(['data' => $this->payload($product, true)]);
    }

    public function uploadImage(Request $request, Branch $branch, CmsImageProcessor $processor): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        try {
            $media = $processor->store($data['image'], $request->user());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['image' => $exception->getMessage()]);
        }

        return response()->json(['data' => $this->mediaData($media, $branch)], 201);
    }

    public function mediaPreview(Branch $branch, CmsMedia $cmsMedia, string $variant)
    {
        $variant = str_replace('-', '_', $variant);
        $path = $cmsMedia->variants[$variant]['path'] ?? ($variant === 'original' ? $cmsMedia->original_path : null);
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $mime = $cmsMedia->variants[$variant]['mime'] ?? $cmsMedia->mime_type;

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function mediaData(CmsMedia $media, Branch $branch): array
    {
        return [
            'id' => $media->id,
            'name' => $media->original_name,
            'mime_type' => $media->mime_type,
            'width' => $media->width,
            'height' => $media->height,
            'preferred_variant' => $media->preferred_variant,
            'preview_url' => $this->mediaPreviewUrl($media, $branch->id),
            'variants' => collect($media->variants)->map(fn (array $variant, string $key): array => [
                'key' => $key,
                'url' => route('branch.product.media.preview', [$branch->id, $media->id, str_replace('_', '-', $key)]),
                'width' => $variant['width'],
                'height' => $variant['height'],
                'mime_type' => $variant['mime'],
                'bytes' => $variant['bytes'] ?? null,
                'is_preferred' => $key === $media->preferred_variant,
            ])->values()->all(),
        ];
    }

    private function mediaPreviewUrl(?CmsMedia $media, string $branchId): ?string
    {
        if (! $media) {
            return null;
        }

        $variant = $media->preferred_variant
            ?? (isset($media->variants['960_webp']) ? '960_webp' : (isset($media->variants['960_avif']) ? '960_avif' : 'original'));

        return route('branch.product.media.preview', [$branchId, $media->id, str_replace('_', '-', $variant)]);
    }

    public function store(StoreProductRequest $request, Branch $branch): JsonResponse
    {
        $data = $request->validated();
        $sellingPriceMinor = Money::toMinor((string) $data['selling_price'], $branch->currency_code);
        $reorderLevel = $data['reorder_level'] ?? null;

        unset($data['selling_price'], $data['reorder_level']);

        $product = DB::transaction(function () use ($data, $branch, $request, $sellingPriceMinor, $reorderLevel): Product {
            $product = Product::create([
                ...$data,
                'branch_id' => $branch->id,
                'selling_price_minor' => $sellingPriceMinor,
                'currency_code' => $branch->currency_code,
                'track_stock' => $data['track_stock'] ?? true,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $product->stockLevel()->create([
                'branch_id' => $branch->id,
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'reorder_level' => $reorderLevel,
            ]);

            $product->load(['category:id,name,is_active', 'baseUnit:id,code,name,symbol,allow_decimal', 'stockLevel', 'publicImage', 'units.unit']);
            $product->loadCount('units');

            $this->audit->log(
                'product.created',
                $product,
                null,
                $this->snapshot($product),
                $branch->id,
                $request->user(),
            );

            return $product;
        });

        return response()->json(['data' => $this->payload($product, true)], 201);
    }

    public function update(UpdateProductRequest $request, Branch $branch, Product $product): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('selling_price', $data)) {
            $data['selling_price_minor'] = Money::toMinor((string) $data['selling_price'], $branch->currency_code);
            unset($data['selling_price']);
        }

        $reorderLevelProvided = array_key_exists('reorder_level', $data);
        $reorderLevel = $data['reorder_level'] ?? null;
        unset($data['reorder_level']);

        $product = DB::transaction(function () use ($data, $branch, $product, $request, $reorderLevel, $reorderLevelProvided): Product {
            $product->load('stockLevel');

            if (array_key_exists('base_unit_id', $data) && $data['base_unit_id'] !== $product->base_unit_id) {
                $stock = $product->stockLevel;
                $hasStock = $stock
                    && (bccomp((string) $stock->quantity_on_hand, '0', 4) !== 0
                        || bccomp((string) $stock->quantity_reserved, '0', 4) !== 0);

                if ($product->units()->exists() || $hasStock) {
                    throw ValidationException::withMessages([
                        'base_unit_id' => 'The base unit cannot be changed while alternate units or stock exist.',
                    ]);
                }
            }

            $before = $this->snapshot($product);

            $product->update($data);

            if ($reorderLevelProvided) {
                $product->stockLevel()->updateOrCreate(
                    ['branch_id' => $branch->id, 'product_id' => $product->id],
                    ['reorder_level' => $reorderLevel],
                );
            }

            $product->refresh();
            $product->load(['category:id,name,is_active', 'baseUnit:id,code,name,symbol,allow_decimal', 'stockLevel', 'publicImage', 'units.unit']);
            $product->loadCount('units');

            $this->audit->log(
                'product.updated',
                $product,
                $before,
                $this->snapshot($product),
                $branch->id,
                $request->user(),
            );

            return $product;
        });

        return response()->json(['data' => $this->payload($product, true)]);
    }

    public function destroy(Request $request, Branch $branch, Product $product): Response
    {
        DB::transaction(function () use ($branch, $product, $request): void {
            $before = $this->snapshot($product);

            $product->update(['is_active' => false]);

            $this->audit->log(
                'product.archived',
                $product,
                $before,
                $this->snapshot($product->refresh()),
                $branch->id,
                $request->user(),
            );
        });

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Product $product, bool $detailed = false): array
    {
        $stock = $product->stockLevel;
        $reorderLevel = $stock?->reorder_level;
        $isLow = $product->track_stock
            && $stock
            && $reorderLevel !== null
            && bccomp((string) $stock->quantity_on_hand, (string) $reorderLevel, 4) <= 0;

        $payload = [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'description' => $product->description,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'is_active' => $product->category->is_active,
            ] : null,
            'base_unit' => $product->baseUnit ? [
                'id' => $product->baseUnit->id,
                'code' => $product->baseUnit->code,
                'name' => $product->baseUnit->name,
                'symbol' => $product->baseUnit->symbol,
                'allow_decimal' => $product->baseUnit->allow_decimal,
                'is_active' => $product->baseUnit->is_active,
            ] : null,
            'selling_price_minor' => $product->selling_price_minor,
            'selling_price' => Money::format($product->selling_price_minor, $product->currency_code),
            'currency_code' => $product->currency_code,
            'track_stock' => $product->track_stock,
            'is_active' => $product->is_active,
            'public_slug' => $product->public_slug,
            'public_description' => $product->public_description,
            'is_public' => $product->is_public,
            'public_image_id' => $product->public_image_id,
            'public_image_url' => $product->public_image_url,
            'public_image_preview_url' => $this->mediaPreviewUrl($product->publicImage, $product->branch_id),
            'public_image_alt' => $product->public_image_alt,
            'stock' => [
                'quantity_on_hand' => $stock?->quantity_on_hand ?? '0.0000',
                'quantity_reserved' => $stock?->quantity_reserved ?? '0.0000',
                'reorder_level' => $reorderLevel,
                'is_low' => $isLow,
            ],
            'alternate_unit_count' => $product->units_count ?? $product->units->count(),
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];

        if ($detailed) {
            $payload['alternate_units'] = $product->units
                ->sortBy(fn (ProductUnit $unit): string => $unit->unit?->name ?? '')
                ->values()
                ->map(fn (ProductUnit $unit): array => [
                    'id' => $unit->id,
                    'unit' => $unit->unit ? [
                        'id' => $unit->unit->id,
                        'code' => $unit->unit->code,
                        'name' => $unit->unit->name,
                        'allow_decimal' => $unit->unit->allow_decimal,
                        'is_active' => $unit->unit->is_active,
                    ] : null,
                    'conversion_factor' => $unit->conversion_factor,
                    'selling_price_minor' => $unit->selling_price_minor,
                    'selling_price' => Money::format($unit->selling_price_minor, $product->currency_code),
                    'is_active' => $unit->is_active,
                ])
                ->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Product $product): array
    {
        return [
            'branch_id' => $product->branch_id,
            'category_id' => $product->category_id,
            'base_unit_id' => $product->base_unit_id,
            'sku' => $product->sku,
            'name' => $product->name,
            'barcode' => $product->barcode,
            'selling_price_minor' => $product->selling_price_minor,
            'currency_code' => $product->currency_code,
            'track_stock' => $product->track_stock,
            'is_active' => $product->is_active,
            'public_slug' => $product->public_slug,
            'public_description' => $product->public_description,
            'is_public' => $product->is_public,
            'public_image_id' => $product->public_image_id,
            'public_image_url' => $product->public_image_url,
            'public_image_alt' => $product->public_image_alt,
        ];
    }
}
