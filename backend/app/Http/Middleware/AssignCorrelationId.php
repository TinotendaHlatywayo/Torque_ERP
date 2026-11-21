<?php

namespace App\Http\Middleware;

use App\Support\CorrelationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AssignCorrelationId
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(CorrelationContext::class)->forRequest(
            $request->headers->get((string) config('observability.correlation_header', 'X-Correlation-Id')),
            $request->headers->get((string) config('observability.request_header', 'X-Request-Id')),
        );
        Log::shareContext($context);

        $response = $next($request);
        $response->headers->set((string) config('observability.correlation_header', 'X-Correlation-Id'), $context['correlation_id']);
        $response->headers->set((string) config('observability.request_header', 'X-Request-Id'), $context['request_id']);

        return $response;
    }
}
