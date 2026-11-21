<?php

namespace Tests\Feature\Queues;

use App\Jobs\SendReservationNotification;
use App\Jobs\SendTaskNotification;
use App\Models\Branch;
use App\Models\NotificationDelivery;
use App\Models\Platform;
use App\Models\Task;
use App\Models\User;
use App\Support\CorrelationContext;
use App\Support\StructuredLogger;
use App\Support\TaskService;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\Feature\AccessControl\InteractsWithBranchAccess;
use Tests\TestCase;

class QueueReliabilityTest extends TestCase
{
    use InteractsWithBranchAccess;
    use RefreshDatabase;

    private ?string $logPath = null;

    protected function tearDown(): void
    {
        if ($this->logPath !== null && is_file($this->logPath)) {
            unlink($this->logPath);
        }

        parent::tearDown();
    }

    public function test_notification_jobs_declare_bounded_retry_backoff_timeout_and_unique_lock(): void
    {
        foreach ([new SendTaskNotification('task-delivery'), new SendReservationNotification('reservation-delivery')] as $job) {
            $this->assertInstanceOf(ShouldQueue::class, $job);
            $this->assertInstanceOf(ShouldBeUnique::class, $job);
            $this->assertSame(3, $job->tries);
            $this->assertSame([15, 60], $job->backoff);
            $this->assertSame(30, $job->timeout);
            $this->assertSame(300, $job->uniqueFor);
            $this->assertSame($job->deliveryId, $job->uniqueId());
        }
    }

    public function test_duplicate_notification_dispatches_are_deduplicated_by_the_unique_lock(): void
    {
        config(['queue.default' => 'database']);

        dispatch(new SendTaskNotification('dedupe-stage84'));
        dispatch(new SendTaskNotification('dedupe-stage84'));

        $this->assertSame(1, DB::table('jobs')->count());
        $payload = json_decode((string) DB::table('jobs')->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(SendTaskNotification::class, $payload['data']['commandName']);
    }

    public function test_queue_payload_propagates_correlation_ids_to_the_worker_context(): void
    {
        config(['queue.default' => 'database']);
        app(CorrelationContext::class)->forRequest('stage84-correlation', 'stage84-request');

        dispatch(new SendTaskNotification('correlation-stage84'));

        $payload = json_decode((string) DB::table('jobs')->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('stage84-correlation', $payload['correlation_id']);
        $this->assertSame('stage84-request', $payload['request_id']);

        $job = Queue::connection('database')->pop('default');
        $this->assertNotNull($job);
        event(new JobProcessing('database', $job));

        $context = app(CorrelationContext::class)->context();
        $this->assertSame('stage84-correlation', $context['correlation_id']);
        $this->assertSame('stage84-request', $context['request_id']);
    }

    public function test_failed_job_alert_hook_logs_a_redacted_failure_context(): void
    {
        $path = $this->useTemporaryStructuredLog();
        $job = Mockery::mock(QueueJob::class);
        $job->shouldReceive('getName')->andReturn(SendTaskNotification::class);
        $job->shouldReceive('getQueue')->andReturn('default');

        event(new JobFailed('database', $job, new RuntimeException('connect token=stage84-job-secret')));

        $log = (string) file_get_contents($path);
        $this->assertStringContainsString('queue.job.failed', $log);
        $this->assertStringContainsString('SendTaskNotification', $log);
        $this->assertStringContainsString('"queue":"default"', $log);
        $this->assertStringNotContainsString('stage84-job-secret', $log);
    }

    public function test_failed_notification_delivery_is_marked_failed_and_logged_without_recipient_data(): void
    {
        Queue::fake([SendTaskNotification::class]);
        $path = $this->useTemporaryStructuredLog();
        [$branch, $admin, $assignee] = $this->branchFixture();
        $task = app(TaskService::class)->create($branch, $admin, [
            'title' => 'Stage 8.4 mail failure',
            'assigned_to' => $assignee->id,
        ]);
        $delivery = NotificationDelivery::query()->where('task_id', $task->id)
            ->where('channel', NotificationDelivery::CHANNEL_EMAIL)
            ->where('user_id', $assignee->id)
            ->firstOrFail();

        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('smtp token=stage84-mail-secret'));

        try {
            (new SendTaskNotification($delivery->id))->handle();
            $this->fail('Expected the mail transport failure to propagate.');
        } catch (RuntimeException) {
        }
        (new SendTaskNotification($delivery->id))->failed(new RuntimeException('worker stopped'));

        $this->assertSame(NotificationDelivery::STATUS_FAILED, $delivery->fresh()->status);
        $this->assertStringContainsString('RuntimeException', (string) $delivery->fresh()->last_error);
        $this->assertSame(Task::STATUS_OPEN, $task->fresh()->status);
        $log = (string) file_get_contents($path);
        $this->assertStringContainsString('notification.delivery.retry_scheduled', $log);
        $this->assertStringContainsString('notification.delivery.failed', $log);
        $this->assertStringContainsString($delivery->id, $log);
        $this->assertStringNotContainsString('stage84-mail-secret', $log);
        $this->assertStringNotContainsString('smtp', $log);
    }

    public function test_structured_logs_redact_nested_secrets_and_include_correlation_ids(): void
    {
        $path = $this->useTemporaryStructuredLog();
        app(CorrelationContext::class)->forRequest('redact-correlation', 'redact-request');

        app(StructuredLogger::class)->info('probe.redaction', [
            'credentials' => [
                'password' => 'stage84-password-secret',
                'nested' => ['integration_key' => 'stage84-key-secret'],
            ],
            'safe' => 'visible-value',
        ]);

        $log = (string) file_get_contents($path);
        $this->assertStringContainsString('probe.redaction', $log);
        $this->assertStringContainsString('"correlation_id":"redact-correlation"', $log);
        $this->assertStringContainsString('"request_id":"redact-request"', $log);
        $this->assertStringContainsString('[redacted]', $log);
        $this->assertStringContainsString('visible-value', $log);
        $this->assertStringNotContainsString('stage84-password-secret', $log);
        $this->assertStringNotContainsString('stage84-key-secret', $log);
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
            'name' => 'Queues Branch',
            'code' => 'QUE-'.strtoupper(bin2hex(random_bytes(2))),
            'slug' => 'queues-'.uniqid(),
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

    private function useTemporaryStructuredLog(): string
    {
        $this->logPath = storage_path('logs/stage84-'.Str::uuid().'.log');
        $channel = 'stage84_'.str_replace('-', '_', (string) Str::uuid());
        config([
            'logging.channels.'.$channel => [
                'driver' => 'single',
                'path' => $this->logPath,
                'level' => 'debug',
                'replace_placeholders' => true,
            ],
            'observability.log_channel' => $channel,
            'observability.failed_job_channel' => $channel,
        ]);

        return $this->logPath;
    }
}
