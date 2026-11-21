<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\User;
use App\Support\CheckoutService;
use App\Support\Decimal;
use App\Support\Money;
use App\Support\ReservationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PublicStorefrontController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly ReservationService $reservations,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        $user = User::create([
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'password' => $data['password'],
            'is_active' => true,
            'is_platform_admin' => false,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Your storefront account is ready.',
            ...$this->accountData($user),
        ], 201);
    }

    public function account(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($this->accountData($user));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'You have been signed out.']);
    }

    public function checkout(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.product_slug' => ['required', 'string', 'max:180'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000', 'regex:/^\d+(\.\d{1,4})?$/'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[+0-9() .-]+$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'A valid Idempotency-Key header is required.',
            ]);
        }

        $branch = $this->branch();
        if (! $branch) {
            abort(503, 'No active storefront branch is configured.');
        }

        $customer = $this->customer($branch, $user, $data);
        $checkoutLines = [];

        foreach ($data['lines'] as $line) {
            $product = $this->visibleProduct($branch, (string) $line['product_slug']);
            $quantity = Decimal::trim(bcadd((string) $line['quantity'], '0', 4));
            $available = $this->availableStock($product);

            if (bccomp($available, $quantity, 4) < 0) {
                throw ValidationException::withMessages([
                    'lines' => 'One or more items no longer have enough available stock.',
                ]);
            }

            $checkoutLines[] = [
                'product_id' => $product->id,
                'unit_id' => $product->base_unit_id,
                'quantity' => $quantity,
            ];
        }

        try {
            $sale = $this->checkout->checkout($branch, $user, [
                'customer_id' => $customer->id,
                'lines' => $checkoutLines,
                'payments' => [],
            ], $idempotencyKey);
        } catch (ConflictHttpException $exception) {
            throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
        }

        return response()->json([
            'message' => 'Your order has been placed. Pay on collection when you collect your parts.',
            'order' => $this->orderData($sale->load(['items.product', 'payments', 'receipt'])),
        ], 201);
    }

    private function accountData(User $user): array
    {
        $branch = $this->branch();
        $branchId = $branch?->id;

        $orders = Sale::query()
            ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->where('actor_id', $user->id)
            ->with(['items.product', 'payments', 'receipt'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Sale $sale): array => $this->orderData($sale))
            ->values()
            ->all();

        $reservations = Reservation::query()
            ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->where('actor_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Reservation $reservation): array => $this->reservationData($reservation))
            ->values()
            ->all();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
            ],
            'orders' => $orders,
            'reservations' => $reservations,
        ];
    }

    private function orderData(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'status' => $sale->status,
            'payment_status' => $sale->payment_status,
            'currency_code' => $sale->currency_code,
            'subtotal' => Money::format($sale->subtotal_minor, $sale->currency_code),
            'total' => Money::format($sale->total_minor, $sale->currency_code),
            'total_minor' => $sale->total_minor,
            'receipt_number' => $sale->receipt?->receipt_number,
            'created_at' => $sale->created_at?->toIso8601String(),
            'items' => $sale->items->map(fn ($item): array => [
                'name' => $item->product_name,
                'quantity' => Decimal::trim((string) $item->quantity),
                'total' => Money::format($item->total_minor, $item->currency_code),
            ])->values()->all(),
        ];
    }

    private function reservationData(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'number' => $reservation->reservation_number,
            'product_name' => $reservation->product_name,
            'quantity' => Decimal::trim((string) $reservation->quantity),
            'amount' => Money::format($reservation->amount_minor, $reservation->currency_code),
            'status' => $reservation->status,
            'expires_at' => $reservation->expires_at?->toIso8601String(),
        ];
    }

    private function customer(Branch $branch, User $user, array $data): Customer
    {
        $email = mb_strtolower($user->email);

        $customer = Customer::query()->where('branch_id', $branch->id)->where('email', $email)->first()
            ?? new Customer(['branch_id' => $branch->id, 'email' => $email]);

        $customer->fill([
            'branch_id' => $branch->id,
            'name' => $user->name,
            'email' => $email,
            'phone' => isset($data['phone']) && $data['phone'] !== '' ? trim($data['phone']) : null,
            'address' => isset($data['address']) && $data['address'] !== '' ? trim($data['address']) : null,
            'notes' => isset($data['notes']) && $data['notes'] !== '' ? trim($data['notes']) : null,
            'is_active' => true,
        ]);
        $customer->save();

        return $customer;
    }

    private function branch(): ?Branch
    {
        return Branch::query()->where('slug', config('catalog.public_branch_slug'))
            ->where('status', Branch::STATUS_ACTIVE)->first()
            ?? Branch::query()->where('status', Branch::STATUS_ACTIVE)->orderBy('id')->first();
    }

    private function visibleProduct(Branch $branch, string $slug): Product
    {
        $product = Product::query()->where('branch_id', $branch->id)->where('public_slug', $slug)
            ->where('is_public', true)->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('category_id')
                    ->orWhereHas('category', fn (Builder $category): Builder => $category->where('is_active', true));
            })
            ->with('stockLevel')
            ->firstOrFail();

        if (! $product->base_unit_id) {
            throw ValidationException::withMessages([
                'lines' => 'One or more products do not have a selling unit configured.',
            ]);
        }

        return $product;
    }

    private function availableStock(Product $product): string
    {
        $level = $product->relationLoaded('stockLevel') ? $product->stockLevel : $product->stockLevel()->first();
        if (! $level) {
            return '0';
        }

        return bcsub((string) $level->quantity_on_hand, (string) $level->quantity_reserved, 4);
    }
}
