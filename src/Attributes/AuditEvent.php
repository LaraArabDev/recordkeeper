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
    public function __construct(
        public readonly array $tags = [],
        public readonly bool $capturePayload = false,
    ) {}
}
