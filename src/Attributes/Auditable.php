<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
/**
 * Configure audit behaviour for a model class.
 *
 * Controls which events to track, which fields to include/exclude,
 * privacy modifiers (redact/encrypt), retention, and tags.
 */
final class Auditable
{
    /**
     * @param  list<string>|null  $events  Eloquent events to audit (null = use global config).
     * @param  list<string>  $only  Only audit these attributes (empty = all).
     * @param  list<string>  $exclude  Exclude these attributes from auditing.
     * @param  list<string>  $redact  Attributes to redact (replace with mask).
     * @param  list<string>  $encrypt  Attributes to encrypt before storage.
     * @param  int|null  $retentionDays  Override the global retention period for this model.
     * @param  int|null  $threshold  Maximum number of audit records per model instance.
     * @param  list<string>  $tags  Tags applied to every audit of this model.
     */
    public function __construct(
        public readonly ?array $events = null,
        public readonly array $only = [],
        public readonly array $exclude = [],
        public readonly array $redact = [],
        public readonly array $encrypt = [],
        public readonly ?int $retentionDays = null,
        public readonly ?int $threshold = null,
        public readonly array $tags = [],
    ) {}
}
