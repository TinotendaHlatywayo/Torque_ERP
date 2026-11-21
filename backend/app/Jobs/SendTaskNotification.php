<?php

namespace App\Jobs;

use App\Mail\TaskNotificationMail;
use App\Models\BranchUser;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
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

class SendTaskNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [15, 60];

    public int $uniqueFor = 300;

    public function __construct(public readonly string $deliveryId) {}

    public function handle(): void
    {
        $delivery = NotificationDelivery::query()->with(['task', 'user'])->find($this->deliveryId);
        if (! $delivery || in_array($delivery->status, [
            NotificationDelivery::STATUS_SENT,
            NotificationDelivery::STATUS_SKIPPED,
        ], true)) {
            return;
        }

        if ($delivery->channel === NotificationDelivery::CHANNEL_IN_APP) {
            $this->markSent($delivery);

            return;
        }

        $recipient = $this->recipient($delivery);
        if (! $recipient) {
            $delivery->update([
                'status' => NotificationDelivery::STATUS_SKIPPED,
                'last_error' => 'Recipient is no longer eligible for task notifications.',
            ]);

            return;
        }

        $delivery->increment('attempts');
        $delivery->update(['status' => NotificationDelivery::STATUS_SENDING, 'last_error' => null]);

        try {
            Mail::to($recipient)->send(new TaskNotificationMail($delivery->task, $delivery->event_type, $delivery->title));
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => NotificationDelivery::STATUS_QUEUED,
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

        $this->markSent($delivery, false);
    }

    public function uniqueId(): string
    {
        return $this->deliveryId;
    }

    public function failed(?Throwable $exception): void
    {
        $failure = $exception ?? new \RuntimeException('Unknown failure');
        $updated = NotificationDelivery::query()->whereKey($this->deliveryId)
            ->whereNotIn('status', [NotificationDelivery::STATUS_SENT, NotificationDelivery::STATUS_SKIPPED])
            ->update([
                'status' => NotificationDelivery::STATUS_FAILED,
                'failed_at' => now(),
                'last_error' => 'Task notification failed: '.class_basename($failure),
            ]);
        app(StructuredLogger::class)->error('notification.delivery.failed', [
            'delivery_id' => $this->deliveryId,
            'updated' => $updated > 0,
            'exception' => class_basename($failure),
        ]);
    }

    private function markSent(NotificationDelivery $delivery, bool $countAttempt = true): void
    {
        if ($countAttempt) {
            $delivery->increment('attempts');
        }
        $delivery->update([
            'status' => NotificationDelivery::STATUS_SENT,
            'sent_at' => now(),
            'last_error' => null,
        ]);
    }

    private function recipient(NotificationDelivery $delivery): ?string
    {
        $user = $delivery->user;
        if (! $user?->is_active || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $preference = NotificationPreference::query()
            ->where('branch_id', $delivery->branch_id)
            ->where('user_id', $user->id)
            ->where('event_type', $delivery->event_type)
            ->where('channel', NotificationDelivery::CHANNEL_EMAIL)
            ->value('is_enabled');

        if ($preference === false) {
            return null;
        }

        $membership = BranchUser::query()
            ->where('branch_id', $delivery->branch_id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $membership || (! $membership->hasPermission(Permissions::BRANCH_TASKS_MANAGE) && $user->id !== $delivery->task?->assigned_to)) {
            return null;
        }

        return $user->email;
    }
}
