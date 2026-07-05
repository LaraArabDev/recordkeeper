<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
/**
 * Control outbound HTTP tracking for a job, command, or event class.
 *
 * When present, provides per-class host filtering for outbound HTTP requests.
 * In manual mode, this attribute (or the TracksOutboundHttp trait) is required
 * for any HTTP tracking to occur within the class execution context.
 */
final class TrackHttp
{
    /**
     * @param  list<string>  $includeHosts  Allowlist — only these hosts are tracked (empty = no filter).
     * @param  list<string>  $excludeHosts  Denylist — these hosts are skipped.
     */
    public function __construct(
        public readonly bool $enabled = true,
        public readonly array $includeHosts = [],
        public readonly array $excludeHosts = [],
    ) {}
}
