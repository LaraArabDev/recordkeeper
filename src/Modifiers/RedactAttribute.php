<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Modifiers;

use OwenIt\Auditing\Contracts\AttributeRedactor;

/**
 * Replace an attribute value with the configured privacy mask.
 *
 * Implements the laravel-auditing AttributeRedactor contract.
 */
final class RedactAttribute implements AttributeRedactor
{
    public static function redact(mixed $value): string
    {
        return (string) config('recordkeeper.privacy.mask', '***');
    }
}
