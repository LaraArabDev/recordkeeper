<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Support;

use LaraArabDev\Recordkeeper\DataObjects\HttpFilterConfig;

/**
 * Track outbound HTTP request timing within the context of a parent audit (job).
 *
 * Registered as a singleton; correlates request start/finish events
 * to compute duration and link them to the originating audit record.
 */
final class HttpTracker
{
    private ?int $currentAuditId = null;

    private ?HttpFilterConfig $filterConfig = null;

    /** @var array<int, float> */
    private array $pendingRequests = [];

    /**
     * Set the parent audit ID and optional filter config for correlating outbound HTTP requests.
     *
     * @param  int  $auditId  The parent audit record ID (0 if not yet created).
     * @param  HttpFilterConfig|null  $filterConfig  Per-class host filter rules.
     */
    public function setContext(int $auditId, ?HttpFilterConfig $filterConfig = null): void
    {
        $this->currentAuditId = $auditId;
        $this->filterConfig = $filterConfig;
    }

    /**
     * Clear the current parent audit context and filter config.
     */
    public function clearContext(): void
    {
        $this->currentAuditId = null;
        $this->filterConfig = null;
    }

    /**
     * Get the current parent audit ID, if set.
     *
     * @return int|null The parent audit ID, or null if no context is active.
     */
    public function currentAuditId(): ?int
    {
        return $this->currentAuditId;
    }

    /**
     * Get the current per-class HTTP filter config, if set.
     *
     * @return HttpFilterConfig|null The active filter config, or null if none is set.
     */
    public function currentFilterConfig(): ?HttpFilterConfig
    {
        return $this->filterConfig;
    }

    /**
     * Whether a parent context (audit ID) has been set.
     *
     * @return bool True if a parent audit context is currently active.
     */
    public function hasActiveContext(): bool
    {
        return $this->currentAuditId !== null;
    }

    /**
     * Record the start time of an outbound HTTP request.
     *
     * @param  object  $request  The HTTP request object (used as identity key).
     * @param  float  $startTime  Microtime when the request started.
     */
    public function startRequest(object $request, float $startTime): void
    {
        $this->pendingRequests[spl_object_id($request)] = $startTime;
    }

    /**
     * Complete tracking for an outbound HTTP request and compute duration.
     *
     * @param  object  $request  The HTTP request object (must match a prior startRequest call).
     * @param  float  $endTime  Microtime when the request finished.
     * @return array{duration_ms: int}|null Duration data, or null if no matching start was recorded.
     */
    public function finishRequest(object $request, float $endTime): ?array
    {
        $key = spl_object_id($request);

        if (! isset($this->pendingRequests[$key])) {
            return null;
        }

        $startTime = $this->pendingRequests[$key];
        unset($this->pendingRequests[$key]);

        return [
            'duration_ms' => (int) (($endTime - $startTime) * 1000),
        ];
    }
}
