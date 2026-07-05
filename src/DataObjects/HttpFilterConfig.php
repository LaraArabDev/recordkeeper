<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\DataObjects;

/**
 * Immutable value object carrying per-class HTTP filter rules.
 *
 * Used by #[TrackHttp] attribute and TracksOutboundHttp trait to control
 * which outbound HTTP hosts are tracked for a given job, command, or event.
 */
final readonly class HttpFilterConfig
{
    /**
     * @param  list<string>  $includeHosts  Allowlist — only these hosts are tracked (empty = no filter).
     * @param  list<string>  $excludeHosts  Denylist — these hosts are skipped.
     */
    public function __construct(
        public bool $enabled = true,
        public array $includeHosts = [],
        public array $excludeHosts = [],
    ) {}

    /**
     * Determine whether the given host should be tracked.
     *
     * - If disabled, always returns false.
     * - If includeHosts is set, host must be in the allowlist (takes precedence over excludeHosts).
     * - Otherwise, host must not be in the denylist.
     *
     * @return bool Whether the host is allowed for tracking.
     */
    public function allowsHost(string $host): bool
    {
        if (! $this->enabled) {
            return false;
        }

        if ($this->includeHosts !== []) {
            return in_array($host, $this->includeHosts, true);
        }

        if ($this->excludeHosts !== []) {
            return ! in_array($host, $this->excludeHosts, true);
        }

        return true;
    }
}
