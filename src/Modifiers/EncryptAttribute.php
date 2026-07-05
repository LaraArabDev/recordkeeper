<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Modifiers;

use Illuminate\Support\Facades\Crypt;
use OwenIt\Auditing\Contracts\AttributeRedactor;

/**
 * Encrypt an attribute value before audit storage, prefixed for identification.
 *
 * Values are stored as "__encrypted:<ciphertext>" and can be decrypted
 * via {@see decrypt()} during rollback operations.
 */
final class EncryptAttribute implements AttributeRedactor
{
    private const PREFIX = '__encrypted:';

    public static function redact(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::PREFIX.Crypt::encryptString((string) $value);
    }

    /**
     * Check whether a value is encrypted with the recordkeeper prefix.
     */
    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /**
     * Decrypt an encrypted audit value, stripping the prefix.
     */
    public static function decrypt(string $value): string
    {
        if (! self::isEncrypted($value)) {
            return $value;
        }

        return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
    }
}
