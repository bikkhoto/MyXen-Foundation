<?php

namespace App\Services\MYXN;

use Illuminate\Support\Facades\Log;

/**
 * TracingService
 *
 * OpenTelemetry-based tracing service for MYXN operations.
 * Provides distributed tracing capabilities for debugging and monitoring.
 *
 * @package App\Services\MYXN
 */
class TracingService
{
    /**
     * Whether tracing is enabled.
     *
     * @var bool
     */
    protected bool $enabled;

    /**
     * Service name for tracing.
     *
     * @var string
     */
    protected string $serviceName;

    /**
     * OTLP endpoint.
     *
     * @var string
     */
    protected string $endpoint;

    /**
     * Active spans storage.
     *
     * @var array
     */
    protected array $activeSpans = [];

    /**
     * Create a new tracing service instance.
     */
    public function __construct()
    {
        $config = config('myxn.tracing', []);
        $this->enabled = $config['enabled'] ?? true;
        $this->serviceName = $config['service_name'] ?? 'myxn-financial-service';
        $this->endpoint = $config['endpoint'] ?? 'http://localhost:4318';
    }

    /**
     * Start a new span for tracing.
     *
     * @param string $name
     * @param array $attributes
     * @return string Span ID
     */
    public function startSpan(string $name, array $attributes = []): string
    {
        if (!$this->enabled) {
            return '';
        }

        $spanId = $this->generateSpanId();
        $traceId = $this->getOrCreateTraceId();

        $span = [
            'span_id' => $spanId,
            'trace_id' => $traceId,
            'name' => $name,
            'service' => $this->serviceName,
            'start_time' => microtime(true),
            'attributes' => array_merge($attributes, [
                'service.name' => $this->serviceName,
                'span.kind' => 'internal',
            ]),
            'events' => [],
            'status' => 'ok',
        ];

        $this->activeSpans[$spanId] = $span;

        Log::channel('myxn')->debug('Span started', [
            'span_id' => $spanId,
            'trace_id' => $traceId,
            'name' => $name,
            'attributes' => $attributes,
        ]);

        return $spanId;
    }

    /**
     * Add an event to a span.
     *
     * @param string $spanId
     * @param string $eventName
     * @param array $attributes
     * @return void
     */
    public function addEvent(string $spanId, string $eventName, array $attributes = []): void
    {
        if (!$this->enabled || !isset($this->activeSpans[$spanId])) {
            return;
        }

        $this->activeSpans[$spanId]['events'][] = [
            'name' => $eventName,
            'timestamp' => microtime(true),
            'attributes' => $attributes,
        ];

        Log::channel('myxn')->debug('Span event added', [
            'span_id' => $spanId,
            'event' => $eventName,
            'attributes' => $attributes,
        ]);
    }

    /**
     * Add attributes to a span.
     *
     * @param string $spanId
     * @param array $attributes
     * @return void
     */
    public function addAttributes(string $spanId, array $attributes): void
    {
        if (!$this->enabled || !isset($this->activeSpans[$spanId])) {
            return;
        }

        $this->activeSpans[$spanId]['attributes'] = array_merge(
            $this->activeSpans[$spanId]['attributes'],
            $attributes
        );
    }

    /**
     * Record an exception in a span.
     *
     * @param string $spanId
     * @param \Throwable $exception
     * @return void
     */
    public function recordException(string $spanId, \Throwable $exception): void
    {
        if (!$this->enabled || !isset($this->activeSpans[$spanId])) {
            return;
        }

        $this->activeSpans[$spanId]['status'] = 'error';
        $this->activeSpans[$spanId]['error'] = [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'stack_trace' => $exception->getTraceAsString(),
        ];

        $this->addEvent($spanId, 'exception', [
            'exception.type' => get_class($exception),
            'exception.message' => $exception->getMessage(),
            'exception.stacktrace' => $exception->getTraceAsString(),
        ]);

        Log::channel('myxn')->error('Span exception recorded', [
            'span_id' => $spanId,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);
    }

    /**
     * End a span and export it.
     *
     * @param string $spanId
     * @return void
     */
    public function endSpan(string $spanId): void
    {
        if (!$this->enabled || !isset($this->activeSpans[$spanId])) {
            return;
        }

        $span = $this->activeSpans[$spanId];
        $span['end_time'] = microtime(true);
        $span['duration_ms'] = ($span['end_time'] - $span['start_time']) * 1000;

        // Export the span
        $this->exportSpan($span);

        // Log the completed span
        Log::channel('myxn')->info('Span completed', [
            'span_id' => $spanId,
            'trace_id' => $span['trace_id'],
            'name' => $span['name'],
            'duration_ms' => round($span['duration_ms'], 2),
            'status' => $span['status'],
        ]);

        // Clean up
        unset($this->activeSpans[$spanId]);
    }

    /**
     * Export span to OTLP endpoint.
     *
     * @param array $span
     * @return void
     */
    protected function exportSpan(array $span): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $payload = $this->formatOTLPPayload($span);

            // Async HTTP call to OTLP endpoint
            // Using a queue job for production would be better
            $this->sendToOTLP($payload);
        } catch (\Exception $e) {
            Log::channel('myxn')->warning('Failed to export span', [
                'span_id' => $span['span_id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Format span data for OTLP protocol.
     *
     * @param array $span
     * @return array
     */
    protected function formatOTLPPayload(array $span): array
    {
        return [
            'resourceSpans' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => $this->serviceName]],
                            ['key' => 'service.version', 'value' => ['stringValue' => config('app.version', '1.0.0')]],
                        ],
                    ],
                    'scopeSpans' => [
                        [
                            'scope' => [
                                'name' => 'myxn-tracer',
                                'version' => '1.0.0',
                            ],
                            'spans' => [
                                [
                                    'traceId' => $span['trace_id'],
                                    'spanId' => $span['span_id'],
                                    'name' => $span['name'],
                                    'startTimeUnixNano' => (int)($span['start_time'] * 1e9),
                                    'endTimeUnixNano' => (int)($span['end_time'] * 1e9),
                                    'attributes' => $this->formatAttributes($span['attributes']),
                                    'events' => array_map(function ($event) {
                                        return [
                                            'name' => $event['name'],
                                            'timeUnixNano' => (int)($event['timestamp'] * 1e9),
                                            'attributes' => $this->formatAttributes($event['attributes']),
                                        ];
                                    }, $span['events']),
                                    'status' => [
                                        'code' => $span['status'] === 'ok' ? 1 : 2,
                                        'message' => $span['error']['message'] ?? '',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Format attributes for OTLP.
     *
     * @param array $attributes
     * @return array
     */
    protected function formatAttributes(array $attributes): array
    {
        $formatted = [];

        foreach ($attributes as $key => $value) {
            $formatted[] = [
                'key' => $key,
                'value' => $this->formatAttributeValue($value),
            ];
        }

        return $formatted;
    }

    /**
     * Format a single attribute value for OTLP.
     *
     * @param mixed $value
     * @return array
     */
    protected function formatAttributeValue($value): array
    {
        if (is_string($value)) {
            return ['stringValue' => $value];
        } elseif (is_int($value)) {
            return ['intValue' => (string)$value];
        } elseif (is_float($value)) {
            return ['doubleValue' => $value];
        } elseif (is_bool($value)) {
            return ['boolValue' => $value];
        } elseif (is_array($value)) {
            return ['stringValue' => json_encode($value)];
        }

        return ['stringValue' => (string)$value];
    }

    /**
     * Send payload to OTLP endpoint.
     *
     * @param array $payload
     * @return void
     */
    protected function sendToOTLP(array $payload): void
    {
        try {
            $client = new \GuzzleHttp\Client([
                'timeout' => 5,
                'connect_timeout' => 2,
            ]);

            $client->postAsync($this->endpoint . '/v1/traces', [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (\Exception $e) {
            // Silently fail - tracing should not affect main application
            Log::channel('myxn')->debug('OTLP export failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate a unique span ID.
     *
     * @return string
     */
    protected function generateSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }

    /**
     * Get or create a trace ID for the current request.
     *
     * @return string
     */
    protected function getOrCreateTraceId(): string
    {
        static $traceId = null;

        if ($traceId === null) {
            $traceId = bin2hex(random_bytes(16));
        }

        return $traceId;
    }

    /**
     * Create a trace context for propagation.
     *
     * @param string $spanId
     * @return array
     */
    public function getTraceContext(string $spanId): array
    {
        if (!isset($this->activeSpans[$spanId])) {
            return [];
        }

        $span = $this->activeSpans[$spanId];

        return [
            'traceparent' => sprintf('00-%s-%s-01', $span['trace_id'], $span['span_id']),
            'trace_id' => $span['trace_id'],
            'span_id' => $span['span_id'],
        ];
    }

    /**
     * Get tracing configuration info.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'enabled' => $this->enabled,
            'service_name' => $this->serviceName,
            'endpoint' => $this->endpoint,
        ];
    }
}
