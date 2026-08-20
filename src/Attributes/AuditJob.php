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
    /**
     * Create a new AuditJob attribute instance.
     *
     * @param  bool  $queued  Whether to audit the job queued event.
     * @param  bool  $processing  Whether to audit the job processing event.
     * @param  bool  $processed  Whether to audit the job processed event.
     * @param  bool  $failed  Whether to audit the job failed event.
     * @param  list<string>  $tags  Tags to apply to audits generated from this job.
     */
    public function __construct(
        public readonly bool $queued = true,
        public readonly bool $processing = true,
        public readonly bool $processed = true,
        public readonly bool $failed = true,
        public readonly array $tags = [],
    ) {}
}
