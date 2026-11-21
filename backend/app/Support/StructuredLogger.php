<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

final class StructuredLogger
{
    public function __construct(private readonly ?string $channel = null) {}

    /** @param array<string, mixed> $context */
    public function info(string $event, array $context = []): void
    {
        $this->write('info', $event, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $event, array $context = []): void
    {
        $this->write('warning', $event, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $event, array $context = []): void
    {
        $this->write('error', $event, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function redacted(array $context): array
    {
        $redacted = [];
        $sensitive = array_map('strtolower', (array) config('observability.redacted_keys', []));

        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, $sensitive, true)) {
                $redacted[$key] = '[redacted]';

                continue;
            }
            if (is_array($value)) {
                $redacted[$key] = $this->redacted($value);

                continue;
            }
            if ($value instanceof Throwable) {
                $redacted[$key] = [
                    'class' => class_basename($value),
                    'message' => '[redacted]',
                ];

                continue;
            }
            $redacted[$key] = $value;
        }

        return $redacted;
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $event, array $context): void
    {
        $channel = $this->channel ?? (string) config('observability.log_channel', 'observability');
        $payload = array_merge($this->redacted($context), app(CorrelationContext::class)->context());
        $payload['event'] = $event;

        Log::channel($channel)->log($level, $event, $payload);
    }
}
