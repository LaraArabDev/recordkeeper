<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
/**
 * Exclude specific model attributes from audit tracking.
 *
 * Repeatable: use multiple `#[AuditExclude(...)]` on the same class.
 */
final class AuditExclude
{
    public readonly array $attributes;

    /**
     * Create a new AuditExclude attribute instance.
     *
     * @param  string  ...$attributes  The model attribute names to exclude from auditing.
     */
    public function __construct(string ...$attributes)
    {
        $this->attributes = $attributes;
    }
}
