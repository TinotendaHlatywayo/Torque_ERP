<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Support\Money;
use App\Support\PaynowGateway;
use App\Support\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PublicReservationController extends Controller
{
    public function form(string $publicSlug)
    {
        [$branch, $product] = $this->product($publicSlug);
        $level = $product->stockLevel;
        $available = $product->track_stock && $level
            && bccomp(bcsub((string) $level->quantity_on_hand, (string) $level->quantity_reserved, 4), '0', 4) > 0;

        return response()->view('public.reservation-form', [
            'branch' => $branch,
            'product' => $product,
            'price' => Money::format($product->selling_price_minor, $product->currency_code),
            'available' => $available,
            'idempotencyKey' => (string) Str::uuid(),
        ])->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, ReservationService $reservations, PaynowGateway $paynow)
    {
        $data = $request->validate([
            'product_slug' => ['required', 'string', 'max:180'],
            'idempotency_key' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[+0-9() .-]+$/', 'required_without:email'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:1000000', 'regex:/^\d+(\.\d{1,4})?$/'],
        ]);
        [$branch, $product] = $this->product($data['product_slug']);
        abort_if(
            (app()->isProduction() || $paynow->enabled()) && ! $paynow->readyFor($product->currency_code),
            503,
            'Paynow checkout requires a configured merchant account, matching currency, and public HTTPS APP_URL.',
        );

        try {
            $result = $reservations->create($branch, $product, [
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'quantity' => $data['quantity'],
            ], $data['idempotency_key']);
        } catch (ConflictHttpException $exception) {
            throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
        }

        $redirectUrl = $paynow->start($result['reservation'], $result['token']);
        if ($redirectUrl) {
            return redirect()->away($redirectUrl);
        }

        return redirect()->route('reservation.status', $result['token'])->with('notice',
            $paynow->enabled()
                ? 'Paynow could not start checkout. Your short stock hold remains active until it expires.'
                : 'Paynow checkout is not configured in this local preview. Your short stock hold remains active.');
    }

    public function status(string $token, ReservationService $reservations, PaynowGateway $paynow)
    {
        $reservation = $reservations->findByToken($token);
        $reservation->load('payment');
        if ($reservation->status === 'awaiting_payment' && $reservation->expires_at->isPast()) {
            $reservations->expireDue();
            $reservation->refresh();
        }

        return response()->view('public.reservation-status', [
            'reservation' => $reservation,
            'token' => $token,
            'price' => Money::format($reservation->amount_minor, $reservation->currency_code),
            'paynowEnabled' => $paynow->readyFor($reservation->currency_code),
        ])->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'private, no-store');
    }

    public function paynowReturn(string $token, ReservationService $reservations, PaynowGateway $paynow)
    {
        $reservation = $reservations->findByToken($token);
        $paynow->refresh($reservation);

        return $this->status($token, $reservations, $paynow);
    }

    public function cancel(string $token, ReservationService $reservations)
    {
        $reservation = $reservations->cancel($token);

        return redirect()->route('reservation.status', $token)->with('notice',
            $reservation->status === 'cancelled' ? 'Your reservation was cancelled and its stock hold was released.' : 'This reservation has already expired.');
    }

    private function product(string $slug): array
    {
        $branch = Branch::query()->where('slug', config('catalog.public_branch_slug'))
            ->where('status', Branch::STATUS_ACTIVE)->firstOrFail();
        $product = Product::query()->where('branch_id', $branch->id)->where('public_slug', $slug)
            ->where('is_public', true)->where('is_active', true)->where('track_stock', true)
            ->with(['category:id,name,slug,is_active', 'stockLevel'])->firstOrFail();
        abort_if($product->category && ! $product->category->is_active, 404);

        return [$branch, $product];
    }
}
