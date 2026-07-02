<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
/**
 * Mark model attributes for encryption before audit storage.
 *
 * Encrypted values are automatically decrypted during rollback.
 * Repeatable: use multiple `#[Encrypt(...)]` on the same class.
 */
final class Encrypt
{
    public readonly array $attributes;

    public function __construct(string ...$attributes)
    {
        $this->attributes = $attributes;
    }
}
