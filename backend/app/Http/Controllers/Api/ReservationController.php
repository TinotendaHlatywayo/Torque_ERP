<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Reservation;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(Request $request, Branch $branch): JsonResponse
    {
        $status = $request->query('status');
        $reservations = $branch->reservations()->with(['product', 'payment', 'notificationDeliveries'])
            ->when(is_string($status) && in_array($status, ['awaiting_payment', 'confirmed', 'ready', 'picked_up', 'expired', 'cancelled', 'refunded'], true),
                fn ($query) => $query->where('status', $status))
            ->latest()->paginate(min(max((int) $request->query('per_page', 25), 1), 100));

        return response()->json([
            'data' => $reservations->getCollection()->map(fn (Reservation $reservation) => $this->payload($reservation)),
            'meta' => [
                'current_page' => $reservations->currentPage(),
                'last_page' => $reservations->lastPage(),
                'per_page' => $reservations->perPage(),
                'total' => $reservations->total(),
            ],
        ]);
    }

    private function payload(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'reservation_number' => $reservation->reservation_number,
            'product_name' => $reservation->product_name,
            'product_is_active' => $reservation->product?->is_active ?? false,
            'quantity' => (string) $reservation->quantity,
            'amount_minor' => $reservation->amount_minor,
            'amount' => Money::format($reservation->amount_minor, $reservation->currency_code),
            'currency_code' => $reservation->currency_code,
            'customer_name' => $reservation->customer_name,
            'customer_email' => $reservation->customer_email,
            'customer_phone' => $reservation->customer_phone,
            'status' => $reservation->status,
            'payment_status' => $reservation->payment?->status,
            'payment_provider' => $reservation->payment?->provider,
            'payment_reference' => $reservation->payment?->provider_reference,
            'notification_deliveries' => $reservation->notificationDeliveries->map(fn ($delivery) => [
                'event' => $delivery->event,
                'recipient_type' => $delivery->recipient_type,
                'status' => $delivery->status,
                'attempts' => $delivery->attempts,
                'sent_at' => $delivery->sent_at?->toIso8601String(),
                'failed_at' => $delivery->failed_at?->toIso8601String(),
            ])->values(),
            'expires_at' => $reservation->expires_at?->toIso8601String(),
            'created_at' => $reservation->created_at?->toIso8601String(),
        ];
    }
}
