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

    /**
     * Encrypt the given value and prefix it for identification.
     *
     * @param  mixed  $value  The original attribute value.
     * @return string The encrypted string with prefix, or empty string for null/empty values.
     */
    public static function redact(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::PREFIX.Crypt::encryptString((string) $value);
    }

    /**
     * Check whether a value is encrypted with the recordkeeper prefix.
     *
     * @param  string  $value  The value to check.
     * @return bool True if the value starts with the encrypted prefix.
     */
    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /**
     * Decrypt an encrypted audit value, stripping the prefix.
     *
     * @param  string  $value  The encrypted value to decrypt.
     * @return string The decrypted plaintext value, or the original value if not encrypted.
     */
    public static function decrypt(string $value): string
    {
        if (! self::isEncrypted($value)) {
            return $value;
        }

        return Crypt::decryptString(substr($value, strlen(self::PREFIX)));
    }
}
