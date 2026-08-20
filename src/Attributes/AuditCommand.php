<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
/**
 * Opt an Artisan command class into audit tracking.
 */
final class AuditCommand
{
    /**
     * Create a new AuditCommand attribute instance.
     *
     * @param  list<string>  $tags  Tags to apply to audits generated from this command.
     */
    public function __construct(
        public readonly array $tags = [],
    ) {}
}
