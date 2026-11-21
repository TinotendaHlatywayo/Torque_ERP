<?php

namespace App\Jobs;

use App\Mail\ReservationStatusMail;
use App\Models\BranchUser;
use App\Models\ReservationNotificationDelivery;
use App\Support\Permissions;
use App\Support\StructuredLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendReservationNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [15, 60];

    public int $uniqueFor = 300;

    public function __construct(public readonly string $deliveryId) {}

    public function handle(): void
    {
        $delivery = ReservationNotificationDelivery::query()->with(['reservation', 'user'])->find($this->deliveryId);
        if (! $delivery || in_array($delivery->status, [
            ReservationNotificationDelivery::STATUS_SENT,
            ReservationNotificationDelivery::STATUS_SKIPPED,
        ], true)) {
            return;
        }

        $recipient = $this->recipient($delivery);
        if (! $recipient) {
            $delivery->update([
                'status' => ReservationNotificationDelivery::STATUS_SKIPPED,
                'last_error' => 'Recipient is no longer eligible for reservation notifications.',
            ]);

            return;
        }

        $delivery->increment('attempts');
        $delivery->update(['status' => ReservationNotificationDelivery::STATUS_SENDING, 'last_error' => null]);

        try {
            Mail::to($recipient)->send(new ReservationStatusMail(
                $delivery->reservation,
                $delivery->event,
                $delivery->recipient_type === ReservationNotificationDelivery::RECIPIENT_BRANCH_USER,
            ));
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => ReservationNotificationDelivery::STATUS_QUEUED,
                'last_error' => 'Mail transport failed: '.class_basename($exception),
            ]);
            app(StructuredLogger::class)->warning('notification.delivery.retry_scheduled', [
                'delivery_id' => $this->deliveryId,
                'channel' => $delivery->channel,
                'attempts' => (int) $delivery->attempts,
                'exception' => class_basename($exception),
            ]);

            throw $exception;
        }

        $delivery->update([
            'status' => ReservationNotificationDelivery::STATUS_SENT,
            'sent_at' => now(),
            'last_error' => null,
        ]);
    }

    public function uniqueId(): string
    {
        return $this->deliveryId;
    }

    public function failed(?Throwable $exception): void
    {
        $failure = $exception ?? new \RuntimeException('Unknown failure');
        $updated = ReservationNotificationDelivery::query()->whereKey($this->deliveryId)
            ->whereNotIn('status', [ReservationNotificationDelivery::STATUS_SENT, ReservationNotificationDelivery::STATUS_SKIPPED])
            ->update([
                'status' => ReservationNotificationDelivery::STATUS_FAILED,
                'failed_at' => now(),
                'last_error' => 'Mail delivery failed: '.class_basename($failure),
            ]);
        app(StructuredLogger::class)->error('notification.delivery.failed', [
            'delivery_id' => $this->deliveryId,
            'updated' => $updated > 0,
            'exception' => class_basename($failure),
        ]);
    }

    private function recipient(ReservationNotificationDelivery $delivery): ?string
    {
        if ($delivery->recipient_type === ReservationNotificationDelivery::RECIPIENT_CUSTOMER) {
            return filter_var($delivery->reservation->customer_email, FILTER_VALIDATE_EMAIL) ?: null;
        }

        if (! $delivery->user?->is_active) {
            return null;
        }

        $membership = BranchUser::query()->where('branch_id', $delivery->branch_id)->where('user_id', $delivery->user_id)
            ->where('is_active', true)->first();

        return $membership?->hasPermission(Permissions::BRANCH_RESERVATIONS_MANAGE) ? $delivery->user->email : null;
    }
}
