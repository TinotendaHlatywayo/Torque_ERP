<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CorrelationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $databaseOk = $this->databaseIsAvailable();
        $queue = $this->queueStatus();
        $healthy = $databaseOk && $queue['ok'];

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => [
                'database' => ['ok' => $databaseOk],
                'queue' => $queue,
            ],
            'failed_jobs' => $this->failedJobCount(),
            'correlation_id' => app(CorrelationContext::class)->context()['correlation_id'],
        ], $healthy ? 200 : 503);
    }

    private function databaseIsAvailable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array{ok: bool, pending: int|null} */
    private function queueStatus(): array
    {
        try {
            return ['ok' => true, 'pending' => Queue::size()];
        } catch (Throwable) {
            return ['ok' => false, 'pending' => null];
        }
    }

    /** @return array{count: int|null} */
    private function failedJobCount(): array
    {
        try {
            return ['count' => DB::table((string) config('queue.failed.table', 'failed_jobs'))->count()];
        } catch (Throwable) {
            return ['count' => null];
        }
    }
}
