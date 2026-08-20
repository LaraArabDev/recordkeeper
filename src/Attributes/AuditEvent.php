<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
/**
 * Opt an application event class into audit tracking, with optional payload capture.
 */
final class AuditEvent
{
    /**
     * Create a new AuditEvent attribute instance.
     *
     * @param  list<string>  $tags  Tags to apply to audits generated from this event.
     * @param  bool  $capturePayload  Whether to capture the event's public properties as audit payload.
     */
    public function __construct(
        public readonly array $tags = [],
        public readonly bool $capturePayload = false,
    ) {}
}
