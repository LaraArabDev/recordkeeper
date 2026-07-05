<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Concerns;

/**
 * Opt a job, command, or event class into outbound HTTP tracking with per-class host filtering.
 *
 * Override any method to customize behavior per class.
 * When both this trait and the #[TrackHttp] attribute are present, the attribute wins.
 *
 * WARNING: Do not reference constructor properties in these methods — the listener
 * may instantiate the class via ReflectionClass::newInstanceWithoutConstructor().
 */
trait TracksOutboundHttp
{
    /**
     * Whether outbound HTTP tracking is enabled for this class.
     */
    public function shouldTrackHttp(): bool
    {
        return true;
    }

    /**
     * Allowlist of hosts to track. Empty means no filter (track all).
     *
     * @return list<string>
     */
    public function httpIncludeHosts(): array
    {
        return [];
    }

    /**
     * Denylist of hosts to exclude from tracking.
     *
     * @return list<string>
     */
    public function httpExcludeHosts(): array
    {
        return [];
    }
}
