<?php

namespace App\Http\Middleware;

use App\Services\MYXN\TracingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TracingMiddleware
{
    public function __construct(
        protected TracingService $tracingService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip tracing if disabled
        if (!config('myxn.tracing.enabled', false)) {
            return $next($request);
        }

        // Extract trace context from headers if present
        $traceId = $request->header('X-Trace-Id');
        $parentSpanId = $request->header('X-Span-Id');

        // Start request span
        $span = $this->tracingService->startSpan('http.request', [
            'http.method' => $request->method(),
            'http.url' => $request->fullUrl(),
            'http.route' => $request->route()?->uri() ?? 'unknown',
            'http.user_agent' => $request->userAgent(),
            'http.client_ip' => $request->ip(),
            'user.id' => $request->user()?->id,
        ]);

        // Store trace context in request for downstream services
        $context = $this->tracingService->getContext();
        $request->attributes->set('trace_id', $context['trace_id'] ?? null);
        $request->attributes->set('span_id', $context['span_id'] ?? null);

        try {
            $response = $next($request);

            // Record response info
            $this->tracingService->recordEvent($span, 'http.response', [
                'http.status_code' => $response->getStatusCode(),
            ]);

            // Set span status based on response code
            if ($response->getStatusCode() >= 400) {
                $this->tracingService->setSpanStatus($span, 'error', "HTTP {$response->getStatusCode()}");
            } else {
                $this->tracingService->setSpanStatus($span, 'ok');
            }

            // Add trace headers to response
            if ($context['trace_id'] ?? null) {
                $response->headers->set('X-Trace-Id', $context['trace_id']);
            }

            return $response;
        } catch (\Exception $e) {
            $this->tracingService->setSpanStatus($span, 'error', $e->getMessage());
            $this->tracingService->recordEvent($span, 'exception', [
                'exception.type' => get_class($e),
                'exception.message' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            $this->tracingService->endSpan($span);
        }
    }
}
