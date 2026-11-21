<?php

namespace App\Support;

use Illuminate\Support\Str;

final class CorrelationContext
{
    private ?string $correlationId = null;

    private ?string $requestId = null;

    /** @return array{correlation_id: string, request_id: string} */
    public function forRequest(?string $correlationId, ?string $requestId): array
    {
        $this->correlationId = $this->validId($correlationId) ? $correlationId : (string) Str::uuid();
        $this->requestId = $this->validId($requestId) ? $requestId : (string) Str::uuid();

        return $this->context();
    }

    public function forJob(?string $correlationId, ?string $requestId): void
    {
        if ($this->validId($correlationId)) {
            $this->correlationId = $correlationId;
        }
        if ($this->validId($requestId)) {
            $this->requestId = $requestId;
        }
    }

    /** @return array{correlation_id: string, request_id: string} */
    public function context(): array
    {
        return [
            'correlation_id' => $this->correlationId ??= (string) Str::uuid(),
            'request_id' => $this->requestId ??= (string) Str::uuid(),
        ];
    }

    private function validId(?string $value): bool
    {
        return $value !== null
            && $value !== ''
            && strlen($value) <= 128
            && preg_match('/^[A-Za-z0-9._:-]+$/', $value) === 1;
    }
}
