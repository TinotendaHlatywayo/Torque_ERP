<?php

namespace App\Providers;

use App\Support\CorrelationContext;
use App\Support\StructuredLogger;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CorrelationContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        Queue::createPayloadUsing(function (): array {
            return app(CorrelationContext::class)->context();
        });

        Queue::before(function (JobProcessing $event): void {
            $payload = $event->job->payload();
            app(CorrelationContext::class)->forJob(
                isset($payload['correlation_id']) && is_string($payload['correlation_id']) ? $payload['correlation_id'] : null,
                isset($payload['request_id']) && is_string($payload['request_id']) ? $payload['request_id'] : null,
            );
        });

        Queue::failing(function (JobFailed $event): void {
            app(StructuredLogger::class, ['channel' => config('observability.failed_job_channel')])->error('queue.job.failed', [
                'job' => $event->job->getName(),
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'exception' => class_basename($event->exception),
            ]);
        });
    }
}
