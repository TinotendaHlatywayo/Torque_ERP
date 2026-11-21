<?php

namespace Tests\Feature\Tasks;

use App\Jobs\SendTaskNotification;
use App\Mail\TaskNotificationMail;
use App\Models\Branch;
use App\Models\NotificationDelivery;
use App\Models\Platform;
use App\Models\Task;
use App\Models\User;
use App\Support\NotificationService;
use App\Support\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class TaskNotificationTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    public function test_task_creation_records_event_and_queues_notifications(): void
    {
        Queue::fake([SendTaskNotification::class]);
        [$branch, $admin, $assignee] = $this->branchFixture();

        $response = $this->withAuthToken($admin)
            ->postJson("/api/branches/{$branch->id}/tasks", [
                'title' => 'Inspect brakes',
                'assigned_to' => $assignee->id,
                'priority' => 'high',
                'due_at' => '2026-12-01T10:00:00Z',
            ])
            ->assertCreated();

        $taskId = $response->json('data.id');
        $this->assertDatabaseHas('task_events', [
            'task_id' => $taskId,
            'event_type' => TaskService::EVENT_CREATED,
        ]);
        $this->assertSame(4, NotificationDelivery::where('task_id', $taskId)->count());
        $this->assertDatabaseHas('notification_deliveries', [
            'task_id' => $taskId,
            'user_id' => $assignee->id,
            'channel' => NotificationDelivery::CHANNEL_IN_APP,
            'event_type' => NotificationService::EVENT_CREATED,
        ]);
        Queue::assertPushed(SendTaskNotification::class, 4);
    }

    public function test_task_events_are_distinct_from_completion_history(): void
    {
        Queue::fake([SendTaskNotification::class]);
        [$branch, $admin, $assignee] = $this->branchFixture();
        $task = app(TaskService::class)->create($branch, $admin, [
            'title' => 'Inspect tyres',
            'assigned_to' => $assignee->id,
        ]);

        $this->withAuthToken($admin)
            ->postJson("/api/branches/{$branch->id}/tasks/{$task->id}/complete", ['note' => 'Done'])
            ->assertOk()
            ->assertJsonPath('data.status', Task::STATUS_COMPLETED);

        $this->assertSame(1, $task->completions()->count());
        $this->assertSame(2, $task->events()->count());
        $this->assertDatabaseHas('task_events', [
            'task_id' => $task->id,
            'event_type' => TaskService::EVENT_COMPLETED,
        ]);
        $this->assertDatabaseHas('task_completions', [
            'task_id' => $task->id,
            'actor_id' => $admin->id,
            'note' => 'Done',
        ]);
    }

    public function test_notification_delivery_sends_once_and_failure_never_changes_the_task(): void
    {
        Queue::fake([SendTaskNotification::class]);
        Mail::fake();
        [$branch, $admin, $assignee] = $this->branchFixture();
        $task = app(TaskService::class)->create($branch, $admin, [
            'title' => 'Service generator',
            'assigned_to' => $assignee->id,
        ]);

        $emailDelivery = NotificationDelivery::where('task_id', $task->id)
            ->where('channel', NotificationDelivery::CHANNEL_EMAIL)
            ->where('user_id', $assignee->id)
            ->firstOrFail();

        (new SendTaskNotification($emailDelivery->id))->handle();
        (new SendTaskNotification($emailDelivery->id))->handle();

        $this->assertSame(NotificationDelivery::STATUS_SENT, $emailDelivery->fresh()->status);
        $this->assertSame(1, $emailDelivery->fresh()->attempts);
        Mail::assertSent(TaskNotificationMail::class, 1);

        (new SendTaskNotification($emailDelivery->id))->failed(new \RuntimeException('Transport unavailable'));
        $this->assertSame(NotificationDelivery::STATUS_SENT, $emailDelivery->fresh()->status);
        $this->assertSame(Task::STATUS_OPEN, $task->fresh()->status);
    }

    public function test_preferences_disable_email_and_notifications_can_be_marked_read(): void
    {
        Queue::fake([SendTaskNotification::class]);
        [$branch, $admin, $assignee] = $this->branchFixture();

        $this->withAuthToken($assignee)
            ->putJson("/api/branches/{$branch->id}/notification-preferences", [
                'preferences' => [[
                    'event_type' => NotificationService::EVENT_CREATED,
                    'channel' => NotificationDelivery::CHANNEL_EMAIL,
                    'is_enabled' => false,
                ]],
            ])
            ->assertOk();

        $task = app(TaskService::class)->create($branch, $admin, [
            'title' => 'Count tyres',
            'assigned_to' => $assignee->id,
        ]);

        $this->assertDatabaseMissing('notification_deliveries', [
            'task_id' => $task->id,
            'user_id' => $assignee->id,
            'channel' => NotificationDelivery::CHANNEL_EMAIL,
        ]);

        $delivery = NotificationDelivery::where('task_id', $task->id)
            ->where('user_id', $assignee->id)
            ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
            ->firstOrFail();

        $this->withAuthToken($assignee)
            ->postJson("/api/branches/{$branch->id}/notifications/{$delivery->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read_at', fn ($value): bool => is_string($value));
    }

    /** @return array{0: Branch, 1: User, 2: User} */
    private function branchFixture(): array
    {
        $platform = Platform::create([
            'name' => 'TMS',
            'slug' => 'tms-'.uniqid(),
            'currency_code' => 'USD',
        ]);
        $branch = Branch::create([
            'platform_id' => $platform->id,
            'name' => 'Tasks Branch',
            'code' => 'TSK-'.strtoupper(bin2hex(random_bytes(2))),
            'slug' => 'tasks-'.uniqid(),
            'status' => Branch::STATUS_ACTIVE,
            'timezone' => 'Africa/Harare',
            'currency_code' => 'USD',
        ]);
        $admin = $this->user('Admin');
        $assignee = $this->user('Assignee');
        $this->membership($branch, $admin, 'admin');
        $this->membership($branch, $assignee, 'cashier');

        return [$branch, $admin, $assignee];
    }
}
