<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Actions;

use LaraArabDev\Recordkeeper\Modifiers\RedactAttribute;

/**
 * Redact sensitive values in an associative array based on key pattern matching.
 */
final class RedactValues
{
    /**
     * Redact sensitive values in the given associative array.
     *
     * Falls back to configured sensitive patterns when none are provided.
     *
     * @param  array<string, mixed>  $values  The key-value pairs to inspect and redact.
     * @param  list<string>  $patterns  Optional list of case-insensitive substring patterns to match against keys.
     * @return array<string, mixed> The array with matching values replaced by redacted placeholders.
     */
    public function __invoke(array $values, array $patterns = []): array
    {
        if (empty($patterns)) {
            $patterns = config('recordkeeper.privacy.sensitive_patterns', []);
        }

        if (empty($patterns)) {
            return $values;
        }

        $result = [];
        foreach ($values as $key => $value) {
            $result[$key] = $this->matchesPattern($key, $patterns)
                ? RedactAttribute::redact($value)
                : $value;
        }

        return $result;
    }

    /**
     * Check whether a key matches any of the sensitive patterns (case-insensitive).
     *
     * @param  string  $key  The attribute key to check.
     * @param  list<string>  $patterns  The list of substring patterns to match against.
     * @return bool True if the key matches at least one pattern.
     */
    private function matchesPattern(string $key, array $patterns): bool
    {
        $lower = strtolower($key);

        foreach ($patterns as $pattern) {
            if (str_contains($lower, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }
}
