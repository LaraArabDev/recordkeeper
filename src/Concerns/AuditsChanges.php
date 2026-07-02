<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Concerns;

use LaraArabDev\Recordkeeper\Recordkeeper;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use OwenIt\Auditing\Auditable;

/**
 * Add audit trail tracking to an Eloquent model.
 *
 * Resolves configuration from PHP 8 attributes, applies privacy modifiers,
 * attaches batch IDs and tags, and integrates with the laravel-auditing package.
 *
 * @see \LaraArabDev\Recordkeeper\Attributes\Auditable
 */
trait AuditsChanges
{
    use Auditable;

    protected array $auditInclude = [];

    protected array $auditExclude = [];

    protected array $auditEvents = [];

    protected array $attributeModifiers = [];

    protected int $auditThreshold = 0;

    /** Bootstrap audit configuration from PHP 8 attributes and global config. */
    public function initializeAuditsChanges(): void
    {
        $resolved = AttributeResolver::resolve($this);

        $this->auditInclude = $resolved->auditInclude;
        $this->auditExclude = $resolved->auditExclude;
        $this->auditEvents = $resolved->auditEvents;
        $this->attributeModifiers = $resolved->attributeModifiers;
        $this->auditThreshold = $resolved->auditThreshold;
    }

    /** @return list<string> */
    public function generateTags(): array
    {
        $resolved = AttributeResolver::resolve($this);

        return array_merge(
            $resolved->auditTags,
            app(Recordkeeper::class)->currentTags(),
        );
    }

    /**
     * Transform audit data before persistence: apply tags, redact sensitive fields, decorate with batch/context.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transformAudit(array $data): array
    {
        $data['tags'] = implode(',', $this->generateTags());

        $privacyMode = config('recordkeeper.privacy.mode', 'redact');

        if ($privacyMode !== 'off') {
            $patterns = config('recordkeeper.privacy.sensitive_patterns', []);

            if (! empty($patterns)) {
                foreach (['new_values', 'old_values'] as $key) {
                    if (! is_array($data[$key] ?? null)) {
                        continue;
                    }
                    foreach ($data[$key] as $attr => $value) {
                        if (isset($this->attributeModifiers[$attr])) {
                            continue;
                        }
                        foreach ($patterns as $pattern) {
                            if (str_contains(strtolower((string) $attr), strtolower($pattern))) {
                                $data[$key][$attr] = config('recordkeeper.privacy.mask', '***');
                                break;
                            }
                        }
                    }
                }
            }
        }

        return app(Recordkeeper::class)->decorate($data);
    }

    /**
     * Push additional context to be included in the next audit for this model.
     *
     * @param  array<string, mixed>  $context
     */
    public function auditContext(array $context): static
    {
        app(Recordkeeper::class)->pushContext($context);

        return $this;
    }
}
