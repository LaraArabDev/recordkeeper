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

    /** @var list<string> */
    protected array $resolvedAuditTags = [];

    /** @var array<string, mixed> */
    protected array $pendingAuditContext = [];

    /**
     * Bootstrap audit configuration from PHP 8 attributes and global config.
     */
    public function initializeAuditsChanges(): void
    {
        $resolved = AttributeResolver::resolve($this);

        $this->auditInclude = $resolved->auditInclude;
        $this->auditExclude = $resolved->auditExclude;
        $this->auditEvents = $resolved->auditEvents;
        $this->attributeModifiers = $resolved->attributeModifiers;
        $this->auditThreshold = $resolved->auditThreshold;
        $this->resolvedAuditTags = $resolved->auditTags;
    }

    /**
     * Merge model-level tags with ambient tags from the Recordkeeper service.
     *
     * @return list<string>
     */
    public function generateTags(): array
    {
        return array_merge(
            $this->resolvedAuditTags,
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
        $recorder = app(Recordkeeper::class);

        $data['tags'] = implode(',', $this->generateTags());
        $data = $this->redactSensitiveValues($data);
        $data = $recorder->decorate($data);

        if (! empty($this->pendingAuditContext)) {
            $data['context'] = array_merge(
                $recorder->normalizeContext($data['context'] ?? []),
                $this->pendingAuditContext,
            );
            $this->pendingAuditContext = [];
        }

        return $data;
    }

    /**
     * Redact values matching configured sensitive patterns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redactSensitiveValues(array $data): array
    {
        if (config('recordkeeper.privacy.mode', 'redact') === 'off') {
            return $data;
        }

        $patterns = config('recordkeeper.privacy.sensitive_patterns', []);

        if (empty($patterns)) {
            return $data;
        }

        $mask = config('recordkeeper.privacy.mask', '***');

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
                        $data[$key][$attr] = $mask;
                        break;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Push additional context to be included in the next audit for this model.
     *
     * @param  array<string, mixed>  $context  The key-value pairs to merge into the audit context.
     */
    public function auditContext(array $context): static
    {
        $this->pendingAuditContext = array_merge($this->pendingAuditContext, $context);

        return $this;
    }
}
