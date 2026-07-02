<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\DataObjects;

/**
 * Resolved audit configuration for a single model, built from PHP attributes and global config.
 */
final readonly class AuditConfig
{
    /**
     * @param  list<string>  $auditInclude
     * @param  list<string>  $auditExclude
     * @param  list<string>  $auditEvents
     * @param  array<string, class-string>  $attributeModifiers
     * @param  list<string>  $auditTags
     */
    public function __construct(
        public array $auditInclude,
        public array $auditExclude,
        public array $auditEvents,
        public array $attributeModifiers,
        public int $auditThreshold,
        public array $auditTags,
        public int $retentionDays,
    ) {}
}
