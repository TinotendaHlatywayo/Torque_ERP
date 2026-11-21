<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class TaskService
{
    public const EVENT_CREATED = 'task_created';

    public const EVENT_UPDATED = 'task_updated';

    public const EVENT_ASSIGNED = 'task_assigned';

    public const EVENT_COMPLETED = 'task_completed';

    public const EVENT_CANCELLED = 'task_cancelled';

    /** @param array<string, mixed> $data */
    public function create(Branch $branch, User $actor, array $data): Task
    {
        return DB::transaction(function () use ($branch, $actor, $data): Task {
            $task = $branch->tasks()->create([
                ...$data,
                'created_by' => $actor->id,
                'status' => Task::STATUS_OPEN,
            ]);

            $this->recordEvent($task, self::EVENT_CREATED, $actor, ['title' => $task->title]);
            app(NotificationService::class)->queueForTask($task, self::EVENT_CREATED, $actor);

            return $task;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Task $task, User $actor, array $data): Task
    {
        return DB::transaction(function () use ($task, $actor, $data): Task {
            $assignmentChanged = array_key_exists('assigned_to', $data)
                && (string) $data['assigned_to'] !== (string) $task->assigned_to;
            $task->update($data);
            $this->recordEvent($task, self::EVENT_UPDATED, $actor, ['changed' => array_keys($data)]);

            if ($assignmentChanged) {
                app(NotificationService::class)->queueForTask($task, self::EVENT_ASSIGNED, $actor);
            }

            return $task->refresh();
        });
    }

    public function complete(Task $task, User $actor, ?string $note): Task
    {
        $this->assertActionable($task);

        return DB::transaction(function () use ($task, $actor, $note): Task {
            $task->completions()->create([
                'branch_id' => $task->branch_id,
                'actor_id' => $actor->id,
                'note' => $note,
                'created_at' => now(),
            ]);
            $task->update([
                'status' => Task::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
            $this->recordEvent($task, self::EVENT_COMPLETED, $actor, ['note' => $note]);
            app(NotificationService::class)->queueForTask($task, self::EVENT_COMPLETED, $actor);

            return $task->refresh();
        });
    }

    public function cancel(Task $task, User $actor, ?string $reason): Task
    {
        $this->assertActionable($task);

        return DB::transaction(function () use ($task, $actor, $reason): Task {
            $task->update(['status' => Task::STATUS_CANCELLED]);
            $this->recordEvent($task, self::EVENT_CANCELLED, $actor, ['reason' => $reason]);
            app(NotificationService::class)->queueForTask($task, self::EVENT_CANCELLED, $actor);

            return $task->refresh();
        });
    }

    private function assertActionable(Task $task): void
    {
        Validator::make(['status' => $task->status], ['status' => 'in:open,in_progress'])->validate();
    }

    /** @param array<string, mixed> $payload */
    private function recordEvent(Task $task, string $eventType, User $actor, array $payload): void
    {
        $task->events()->create([
            'branch_id' => $task->branch_id,
            'event_type' => $eventType,
            'payload' => $payload,
            'actor_id' => $actor->id,
            'created_at' => now(),
        ]);
    }
}
