<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Support;

/**
 * Track outbound HTTP request timing within the context of a parent audit (job).
 *
 * Registered as a singleton; correlates request start/finish events
 * to compute duration and link them to the originating audit record.
 */
final class HttpTracker
{
    private ?int $currentAuditId = null;

    /** @var array<int, float> */
    private array $pendingRequests = [];

    public function setContext(int $auditId): void
    {
        $this->currentAuditId = $auditId;
    }

    public function clearContext(): void
    {
        $this->currentAuditId = null;
    }

    public function currentAuditId(): ?int
    {
        return $this->currentAuditId;
    }

    public function startRequest(object $request, float $startTime): void
    {
        $this->pendingRequests[spl_object_id($request)] = $startTime;
    }

    /** @return array{duration_ms: int}|null */
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
