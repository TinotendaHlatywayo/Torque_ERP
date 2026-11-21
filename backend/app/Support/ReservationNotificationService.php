<?php

namespace App\Support;

use App\Jobs\SendReservationNotification;
use App\Models\BranchUser;
use App\Models\Reservation;
use App\Models\ReservationNotificationDelivery;

final class ReservationNotificationService
{
    public const EVENT_CREATED = 'reservation_created';

    public const EVENT_PAYMENT_CONFIRMED = 'payment_confirmed';

    public function queueCreated(Reservation $reservation): void
    {
        $this->queue($reservation, self::EVENT_CREATED);
    }

    public function queuePaymentConfirmed(Reservation $reservation): void
    {
        $this->queue($reservation, self::EVENT_PAYMENT_CONFIRMED);
    }

    private function queue(Reservation $reservation, string $event): void
    {
        $deliveries = [];

        if (filter_var($reservation->customer_email, FILTER_VALIDATE_EMAIL)) {
            $deliveries[] = $this->upsertDelivery($reservation, $event, ReservationNotificationDelivery::RECIPIENT_CUSTOMER, 'customer');
        }

        BranchUser::query()
            ->where('branch_id', $reservation->branch_id)
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->with('user')
            ->get()
            ->filter(fn (BranchUser $membership): bool => $membership->hasPermission(Permissions::BRANCH_RESERVATIONS_MANAGE))
            ->each(function (BranchUser $membership) use ($reservation, $event, &$deliveries): void {
                $deliveries[] = $this->upsertDelivery(
                    $reservation,
                    $event,
                    ReservationNotificationDelivery::RECIPIENT_BRANCH_USER,
                    'user:'.$membership->user_id,
                    $membership->user_id,
                );
            });

        foreach (array_filter($deliveries) as $delivery) {
            SendReservationNotification::dispatch($delivery->id)->afterCommit();
        }
    }

    private function upsertDelivery(
        Reservation $reservation,
        string $event,
        string $recipientType,
        string $recipientKey,
        ?int $userId = null,
    ): ?ReservationNotificationDelivery {
        $delivery = ReservationNotificationDelivery::query()->firstOrCreate([
            'reservation_id' => $reservation->id,
            'event' => $event,
            'recipient_key' => $recipientKey,
        ], [
            'branch_id' => $reservation->branch_id,
            'user_id' => $userId,
            'recipient_type' => $recipientType,
            'status' => ReservationNotificationDelivery::STATUS_QUEUED,
        ]);

        return $delivery->wasRecentlyCreated ? $delivery : null;
    }
}
