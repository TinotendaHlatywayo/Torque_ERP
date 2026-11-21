<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\CmsMedia;
use App\Models\CmsPageVersion;
use App\Models\CmsSiteContent;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Money;
use App\Support\PaynowGateway;
use App\Support\ReservationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PublicSiteController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly PaynowGateway $paynow,
    ) {}

    public function site(): JsonResponse
    {
        $branch = $this->branch();
        $featured = $branch
            ? $this->visibleProducts($branch)->orderBy('products.name')->limit(4)->get()
            : collect();

        return response()->json([
            'branch' => [
                'name' => $branch?->name ?? config('app.name', 'Taziva Motor Spares'),
                'code' => $branch?->code ?? 'TMS',
                'timezone' => $branch?->timezone ?? 'Africa/Harare',
                'currency_code' => $branch?->currency_code ?? 'USD',
            ],
            'categories' => $this->categories($branch),
            'featured' => $featured->map(fn (Product $product): array => $this->productSummary($product))->values()->all(),
            'reservation_minutes' => max(1, min((int) config('catalog.reservation_expiration_minutes', 15), 1440)),
            'catalogue_ready' => $branch !== null,
            'content' => $this->siteContent(),
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:160'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);
        $branch = $this->branch();
        $query = trim((string) ($data['q'] ?? ''));

        if (! $branch) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => (int) ($data['page'] ?? 1),
                    'last_page' => 1,
                    'per_page' => (int) ($data['per_page'] ?? 12),
                    'total' => 0,
                ],
                'categories' => [],
            ]);
        }

        $products = $this->visibleProducts($branch);

        if (isset($data['category']) && $data['category'] !== '') {
            $category = Category::query()->where('branch_id', $branch->id)->where('slug', $data['category'])
                ->where('is_active', true)
                ->whereHas('products', fn (Builder $builder): Builder => $builder->where('is_active', true)->where('is_public', true))
                ->firstOrFail();
            $products->where('products.category_id', $category->id);
        }

        if ($query !== '') {
            $products->where(function (Builder $search) use ($query): void {
                $search->where('products.name', 'like', '%'.$query.'%')
                    ->orWhere('products.public_description', 'like', '%'.$query.'%');
            });
        }

        $paginator = $products->orderBy('products.name')
            ->paginate((int) ($data['per_page'] ?? 12), ['*'], 'page', (int) ($data['page'] ?? 1))
            ->withQueryString();

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Product $product): array => $this->productSummary($product))->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'categories' => $this->categories($branch),
        ]);
    }

    public function product(string $publicSlug): JsonResponse
    {
        $branch = $this->branch();
        if (! $branch) {
            abort(404);
        }

        $product = Product::query()->where('branch_id', $branch->id)->where('public_slug', $publicSlug)
            ->with(['category:id,name,slug,is_active', 'publicImage', 'stockLevel'])->firstOrFail();
        abort_unless($product->is_public && $product->public_description, 404);
        abort_unless($product->is_active, 410);
        abort_if($product->category && ! $product->category->is_active, 410);

        $related = $this->visibleProducts($branch)
            ->where('products.category_id', $product->category_id)
            ->where('products.id', '<>', $product->id)
            ->orderBy('products.name')->limit(4)->get();

        return response()->json([
            'product' => [
                ...$this->productSummary($product),
                'public_description' => $product->public_description,
            ],
            'related' => $related->map(fn (Product $item): array => $this->productSummary($item))->values()->all(),
            'can_reserve' => $this->canReserve($product),
        ]);
    }

    public function page(string $slug): JsonResponse
    {
        $version = CmsPageVersion::query()->where('slug', $slug)
            ->whereHas('page', function (Builder $query): void {
                $query->whereNotNull('published_version')
                    ->whereColumn('cms_pages.published_version', 'cms_page_versions.version');
            })->firstOrFail();
        $media = CmsMedia::query()->whereIn('id', $version->media_ids ?? [])->get()->keyBy('id');
        $sections = collect($version->sections ?? [])
            ->map(function (array $section) use ($media): array {
                $image = isset($section['media_id']) ? $media->get($section['media_id']) : null;

                return [
                    'type' => $section['type'],
                    'heading' => $section['heading'] ?? '',
                    'body' => $section['body'] ?? '',
                    'image_url' => $this->imageUrl($image),
                    'alt' => $section['alt'] ?? null,
                ];
            })->values()->all();

        return response()->json([
            'page' => [
                'title' => $version->title,
                'slug' => $version->slug,
                'sections' => $sections,
            ],
        ]);
    }

    public function startReservation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_slug' => ['required', 'string', 'max:180'],
        ]);
        $branch = $this->branch();
        if (! $branch) {
            abort(503, 'No active storefront branch is configured.');
        }

        $product = $this->reservationProduct($branch, $data['product_slug']);
        abort_unless($this->canReserve($product), 404);

        return response()->json([
            'idempotency_key' => (string) Str::uuid(),
            'product' => [
                'slug' => $product->public_slug,
                'name' => $product->name,
                'price' => Money::format($product->selling_price_minor, $product->currency_code),
                'currency_code' => $product->currency_code,
                'can_reserve' => true,
            ],
        ]);
    }

    public function createReservation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_slug' => ['required', 'string', 'max:180'],
            'idempotency_key' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[+0-9() .-]+$/', 'required_without:email'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:1000000', 'regex:/^\d+(\.\d{1,4})?$/'],
        ]);
        $branch = $this->branch();
        if (! $branch) {
            abort(503, 'No active storefront branch is configured.');
        }

        $product = $this->reservationProduct($branch, $data['product_slug']);
        abort_if(
            (app()->isProduction() || $this->paynow->enabled()) && ! $this->paynow->readyFor($product->currency_code),
            503,
            'Paynow checkout requires a configured merchant account, matching currency, and public HTTPS APP_URL.',
        );

        $actor = Auth::guard('sanctum')->user();
        if (! $actor instanceof User) {
            $actor = $request->user();
        }

        try {
            $result = $this->reservations->create($branch, $product, [
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'quantity' => $data['quantity'],
            ], $data['idempotency_key'], $actor);
        } catch (ConflictHttpException $exception) {
            throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
        }

        $paymentUrl = $this->paynow->start($result['reservation'], $result['token']);
        $notice = $paymentUrl
            ? 'Continue to Paynow to complete payment for your reservation.'
            : ($this->paynow->enabled()
                ? 'Paynow could not start checkout. Your short stock hold remains active until it expires.'
                : 'Paynow checkout is not configured in this local preview. Your short stock hold remains active.');

        return response()->json([
            'token' => $result['token'],
            'status_url' => route('public.site.reservation.status', $result['token']),
            'payment_url' => $paymentUrl,
            'notice' => $notice,
            'reservation' => $this->createdReservationData($result['reservation']),
        ], 201);
    }

    public function status(string $token): JsonResponse
    {
        $reservation = $this->reservations->findByToken($token);
        $reservation->load('payment');
        if ($reservation->status === Reservation::STATUS_AWAITING_PAYMENT && $reservation->expires_at->isPast()) {
            $this->reservations->expireDue();
            $reservation->refresh();
        }

        return response()->json(['reservation' => $this->statusReservationData($reservation)]);
    }

    public function cancel(string $token): JsonResponse
    {
        $reservation = $this->reservations->cancel($token);
        $reservation->load('payment');

        return response()->json([
            'reservation' => $this->statusReservationData($reservation),
            'notice' => $reservation->status === Reservation::STATUS_CANCELLED
                ? 'Your reservation was cancelled and its stock hold was released.'
                : 'This reservation has already expired.',
        ]);
    }

    private function branch(): ?Branch
    {
        return Branch::query()->where('slug', config('catalog.public_branch_slug'))
            ->where('status', Branch::STATUS_ACTIVE)->first()
            ?? Branch::query()->where('status', Branch::STATUS_ACTIVE)->orderBy('id')->first();
    }

    /** @return array<int, array{name: string, slug: string}> */
    private function siteContent(): array
    {
        $site = CmsSiteContent::query()->where('key', 'default')->first();
        $content = $site ? $site->publicContent() : CmsSiteContent::defaults();
        $images = [];

        foreach (CmsSiteContent::mediaKeys() as $key) {
            $media = isset($content[$key]) && $content[$key] ? CmsMedia::query()->find($content[$key]) : null;
            $images[$key] = $media ? [
                'url' => $this->imageUrl($media),
                'alt' => $content[$this->imageAltKey($key)] ?? '',
            ] : null;
        }

        return ['text' => $content, 'images' => $images];
    }

    private function imageAltKey(string $mediaKey): string
    {
        return match ($mediaKey) {
            'favicon_media_id' => 'logo_alt',
            'logo_media_id' => 'logo_alt',
            'home_hero_media_id' => 'home_hero_image_alt',
            'about_media_id' => 'about_image_alt',
            'services_media_id' => 'services_image_alt',
            'contact_media_id' => 'contact_image_alt',
            default => 'logo_alt',
        };
    }

    private function categories(?Branch $branch): array
    {
        if (! $branch) {
            return [];
        }

        return Category::query()->where('branch_id', $branch->id)->where('is_active', true)
            ->whereHas('products', fn (Builder $builder): Builder => $builder->where('is_active', true)->where('is_public', true))
            ->orderBy('name')->get(['name', 'slug'])
            ->map(fn (Category $category): array => ['name' => $category->name, 'slug' => $category->slug])
            ->all();
    }

    private function visibleProducts(Branch $branch): Builder
    {
        return Product::query()->where('products.branch_id', $branch->id)
            ->where('products.is_active', true)->where('products.is_public', true)
            ->whereNotNull('products.public_slug')->whereNotNull('products.public_description')
            ->where(function (Builder $builder): void {
                $builder->whereNull('products.category_id')
                    ->orWhereHas('category', fn (Builder $category): Builder => $category->where('is_active', true));
            })
            ->with(['category:id,name,slug,is_active', 'publicImage', 'stockLevel']);
    }

    /**
     * @return array{id: string, name: string, slug: string, category_name: ?string, category_slug: ?string, short_description: string, price: string, price_value: int, currency_code: string, image_url: ?string, image_alt: ?string, can_reserve: bool}
     */
    private function productSummary(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->public_slug,
            'category_name' => $product->category?->name,
            'category_slug' => $product->category?->slug,
            'short_description' => Str::limit((string) $product->public_description, 155),
            'price' => Money::format($product->selling_price_minor, $product->currency_code),
            'price_value' => $product->selling_price_minor,
            'currency_code' => $product->currency_code,
            'image_url' => $this->productImageUrl($product),
            'image_alt' => $product->public_image_alt,
            'can_reserve' => $this->canReserve($product),
        ];
    }

    private function imageUrl(?CmsMedia $media): ?string
    {
        if (! $media) {
            return null;
        }

        $variants = $media->variants ?? [];
        $preferred = $media->preferred_variant;
        $variant = $preferred && isset($variants[$preferred])
            ? $preferred
            : (isset($variants['960_webp']) ? '960_webp' : (isset($variants['960_avif']) ? '960_avif' : 'original'));

        return route('public.media', [$media->id, str_replace('_', '-', $variant)]);
    }

    private function productImageUrl(Product $product): ?string
    {
        $imageUrl = $product->public_image_url;

        if (! $imageUrl) {
            return $this->imageUrl($product->publicImage);
        }

        if (str_starts_with($imageUrl, '/') && ! str_starts_with($imageUrl, '//')) {
            return url($imageUrl);
        }

        return $imageUrl;
    }

    private function canReserve(Product $product): bool
    {
        if (! $product->track_stock) {
            return false;
        }

        $level = $product->relationLoaded('stockLevel') ? $product->stockLevel : $product->stockLevel()->first();
        if (! $level) {
            return false;
        }

        return bccomp(bcsub((string) $level->quantity_on_hand, (string) $level->quantity_reserved, 4), '0', 4) > 0;
    }

    private function reservationProduct(Branch $branch, string $slug): Product
    {
        $product = Product::query()->where('branch_id', $branch->id)->where('public_slug', $slug)
            ->where('is_public', true)->where('is_active', true)->where('track_stock', true)
            ->with(['category:id,name,slug,is_active', 'stockLevel'])->firstOrFail();
        abort_if($product->category && ! $product->category->is_active, 404);

        return $product;
    }

    /** @return array{number: string, status: string, amount: string, currency_code: string, expires_at: ?string} */
    private function createdReservationData(Reservation $reservation): array
    {
        return [
            'number' => $reservation->reservation_number,
            'status' => $reservation->status,
            'amount' => Money::format($reservation->amount_minor, $reservation->currency_code),
            'currency_code' => $reservation->currency_code,
            'expires_at' => $reservation->expires_at?->toIso8601String(),
        ];
    }

    /** @return array{number: string, product_name: string, quantity: string, amount: string, currency_code: string, status: string, payment_status: ?string, expires_at: ?string, can_cancel: bool} */
    private function statusReservationData(Reservation $reservation): array
    {
        return [
            'number' => $reservation->reservation_number,
            'product_name' => $reservation->product_name,
            'quantity' => Decimal::trim((string) $reservation->quantity),
            'amount' => Money::format($reservation->amount_minor, $reservation->currency_code),
            'currency_code' => $reservation->currency_code,
            'status' => $reservation->status,
            'payment_status' => $reservation->payment?->status,
            'expires_at' => $reservation->expires_at?->toIso8601String(),
            'can_cancel' => $reservation->status === Reservation::STATUS_AWAITING_PAYMENT && $reservation->expires_at?->isFuture() === true,
        ];
    }
}
