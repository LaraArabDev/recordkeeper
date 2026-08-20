<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
/**
 * Mark model attributes for redaction (replaced with a mask before storage).
 *
 * Repeatable: use multiple `#[Redact(...)]` on the same class.
 */
final class Redact
{
    public readonly array $attributes;

    /**
     * Create a new Redact attribute instance.
     *
     * @param  string  ...$attributes  The model attribute names to redact.
     */
    public function __construct(string ...$attributes)
    {
        $this->attributes = $attributes;
    }
}
