<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Support;

use LaraArabDev\Recordkeeper\Attributes\Auditable;
use LaraArabDev\Recordkeeper\Attributes\AuditExclude;
use LaraArabDev\Recordkeeper\Attributes\Encrypt;
use LaraArabDev\Recordkeeper\Attributes\Redact;
use LaraArabDev\Recordkeeper\DataObjects\AuditConfig;
use LaraArabDev\Recordkeeper\Modifiers\EncryptAttribute;
use LaraArabDev\Recordkeeper\Modifiers\RedactAttribute;
use ReflectionClass;

/**
 * Resolve audit configuration from PHP 8 attributes and global config.
 *
 * Results are cached per class for the lifetime of the request.
 */
final class AttributeResolver
{
    private static array $cache = [];

    /**
     * Resolve and cache the audit configuration for a model class.
     *
     * @param  string|object  $model  FQCN or model instance.
     * @return AuditConfig The resolved audit configuration data object.
     */
    public static function resolve(string|object $model): AuditConfig
    {
        $class = is_object($model) ? $model::class : $model;

        $privacyMode = config('recordkeeper.privacy.mode', 'redact');
        $cacheKey = $class.':'.$privacyMode;

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $ref = new ReflectionClass($class);

        $events = config('recordkeeper.events', ['created', 'updated', 'deleted', 'restored']);
        $include = [];
        $exclude = config('recordkeeper.privacy.global_exclude', ['remember_token']);
        $modifiers = [];
        $threshold = 0;
        $tags = [];
        $retentionDays = (int) config('recordkeeper.retention.default_days', 0);

        $auditableAttrs = $ref->getAttributes(Auditable::class);
        if (! empty($auditableAttrs)) {
            $auditable = $auditableAttrs[0]->newInstance();

            if ($auditable->events !== null) {
                $events = $auditable->events;
            }
            if (! empty($auditable->only)) {
                $include = $auditable->only;
            }
            if (! empty($auditable->exclude)) {
                $exclude = array_merge($exclude, $auditable->exclude);
            }
            foreach ($auditable->redact as $attr) {
                $modifiers[$attr] = RedactAttribute::class;
            }
            foreach ($auditable->encrypt as $attr) {
                $modifiers[$attr] = EncryptAttribute::class;
            }
            if ($auditable->threshold !== null) {
                $threshold = $auditable->threshold;
            }
            if (! empty($auditable->tags)) {
                $tags = $auditable->tags;
            }
            if ($auditable->retentionDays !== null) {
                $retentionDays = $auditable->retentionDays;
            }
        }

        foreach ($ref->getAttributes(AuditExclude::class) as $attrRef) {
            $attr = $attrRef->newInstance();
            $exclude = array_merge($exclude, $attr->attributes);
        }

        foreach ($ref->getAttributes(Redact::class) as $attrRef) {
            $attr = $attrRef->newInstance();
            foreach ($attr->attributes as $field) {
                $modifiers[$field] = RedactAttribute::class;
            }
        }

        foreach ($ref->getAttributes(Encrypt::class) as $attrRef) {
            $attr = $attrRef->newInstance();
            foreach ($attr->attributes as $field) {
                $modifiers[$field] = EncryptAttribute::class;
            }
        }

        if ($privacyMode !== 'off') {
            $patterns = config('recordkeeper.privacy.sensitive_patterns', []);
            $properties = self::guessModelAttributes($model);

            foreach ($properties as $prop) {
                if (isset($modifiers[$prop])) {
                    continue;
                }
                foreach ($patterns as $pattern) {
                    if (str_contains(strtolower($prop), strtolower($pattern))) {
                        $modifiers[$prop] = RedactAttribute::class;
                        break;
                    }
                }
            }
        } else {
            $modifiers = array_filter($modifiers, fn (string $m) => $m !== RedactAttribute::class);
        }

        $config = new AuditConfig(
            auditInclude: $include,
            auditExclude: array_unique($exclude),
            auditEvents: $events,
            attributeModifiers: $modifiers,
            auditThreshold: $threshold,
            auditTags: $tags,
            retentionDays: $retentionDays,
        );

        self::$cache[$cacheKey] = $config;

        return $config;
    }

    /**
     * Clear the in-memory resolution cache.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Infer attribute names from the model's fillable and cast definitions.
     *
     * @param  string|object  $model  FQCN or model instance to inspect.
     * @return list<string> The unique list of inferred attribute names.
     */
    private static function guessModelAttributes(string|object $model): array
    {
        $attributes = [];

        if (is_object($model)) {
            if (method_exists($model, 'getFillable')) {
                $attributes = array_merge($attributes, $model->getFillable());
            }
            if (method_exists($model, 'getCasts')) {
                $attributes = array_merge($attributes, array_keys($model->getCasts()));
            }
        }

        return array_unique($attributes);
    }
}
