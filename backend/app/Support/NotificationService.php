<?php

namespace App\Support;

use App\Jobs\SendTaskNotification;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

final class NotificationService
{
    public const EVENT_CREATED = 'task_created';

    public const EVENT_ASSIGNED = 'task_assigned';

    public const EVENT_COMPLETED = 'task_completed';

    public const EVENT_CANCELLED = 'task_cancelled';

    public const CHANNELS = [NotificationDelivery::CHANNEL_IN_APP, NotificationDelivery::CHANNEL_EMAIL];

    /** @return array<int, string> */
    public static function events(): array
    {
        return [self::EVENT_CREATED, self::EVENT_ASSIGNED, self::EVENT_COMPLETED, self::EVENT_CANCELLED];
    }

    public function queueForTask(Task $task, string $event, ?User $actor = null): int
    {
        $recipientIds = $this->recipientIds($task, $actor);
        $queued = 0;

        foreach ($recipientIds as $userId) {
            $user = User::query()->find($userId);
            if (! $user) {
                continue;
            }

            foreach (self::CHANNELS as $channel) {
                if (! $this->isEnabled($task->branch_id, $user->id, $event, $channel)) {
                    continue;
                }

                $delivery = NotificationDelivery::query()->firstOrCreate([
                    'task_id' => $task->id,
                    'event_type' => $event,
                    'recipient_key' => 'user:'.$user->id,
                    'channel' => $channel,
                ], [
                    'branch_id' => $task->branch_id,
                    'user_id' => $user->id,
                    'title' => $this->title($task, $event),
                    'body' => $task->title,
                    'status' => NotificationDelivery::STATUS_QUEUED,
                ]);

                if (! $delivery->wasRecentlyCreated) {
                    continue;
                }

                SendTaskNotification::dispatch($delivery->id)->afterCommit();
                $queued++;
            }
        }

        return $queued;
    }

    /** @return array<int, array{event_type: string, channel: string, is_enabled: bool}> */
    public function preferences(Branch $branch, User $user): array
    {
        $preferences = [];

        foreach (self::events() as $event) {
            foreach (self::CHANNELS as $channel) {
                $preference = NotificationPreference::query()->firstOrCreate([
                    'branch_id' => $branch->id,
                    'user_id' => $user->id,
                    'event_type' => $event,
                    'channel' => $channel,
                ], ['is_enabled' => true]);

                $preferences[] = [
                    'event_type' => $event,
                    'channel' => $channel,
                    'is_enabled' => (bool) $preference->is_enabled,
                ];
            }
        }

        return $preferences;
    }

    /**
     * @param  array<int, array{event_type: string, channel: string, is_enabled: bool}>  $preferences
     * @return array<int, array{event_type: string, channel: string, is_enabled: bool}>
     */
    public function updatePreferences(Branch $branch, User $user, array $preferences): array
    {
        foreach ($preferences as $preference) {
            NotificationPreference::query()->updateOrCreate([
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'event_type' => $preference['event_type'],
                'channel' => $preference['channel'],
            ], ['is_enabled' => (bool) $preference['is_enabled']]);
        }

        return $this->preferences($branch, $user);
    }

    private function isEnabled(string $branchId, int $userId, string $event, string $channel): bool
    {
        return (bool) (NotificationPreference::query()
            ->where('branch_id', $branchId)
            ->where('user_id', $userId)
            ->where('event_type', $event)
            ->where('channel', $channel)
            ->value('is_enabled') ?? true);
    }

    /** @return Collection<int, int> */
    private function recipientIds(Task $task, ?User $actor): Collection
    {
        $ids = collect([$task->assigned_to, $task->created_by, $actor?->id])
            ->filter()
            ->map(fn ($id): int => (int) $id);

        BranchUser::query()
            ->where('branch_id', $task->branch_id)
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->get()
            ->filter(fn (BranchUser $membership): bool => $membership->hasPermission(Permissions::BRANCH_TASKS_MANAGE))
            ->each(fn (BranchUser $membership) => $ids->push((int) $membership->user_id));

        return $ids->unique()->values();
    }

    private function title(Task $task, string $event): string
    {
        return match ($event) {
            self::EVENT_CREATED => 'New task: '.$task->title,
            self::EVENT_ASSIGNED => 'Task assigned: '.$task->title,
            self::EVENT_COMPLETED => 'Task completed: '.$task->title,
            self::EVENT_CANCELLED => 'Task cancelled: '.$task->title,
            default => 'Task update: '.$task->title,
        };
    }
}
