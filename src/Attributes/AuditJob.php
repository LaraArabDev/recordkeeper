<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
/**
 * Opt a queued job class into audit tracking with per-lifecycle-event control.
 */
final class AuditJob
{
    public function __construct(
        public readonly bool $queued = true,
        public readonly bool $processing = true,
        public readonly bool $processed = true,
        public readonly bool $failed = true,
        public readonly array $tags = [],
    ) {}
}
