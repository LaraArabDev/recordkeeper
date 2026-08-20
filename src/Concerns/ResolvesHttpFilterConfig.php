<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Concerns;

use LaraArabDev\Recordkeeper\Attributes\TrackHttp;
use LaraArabDev\Recordkeeper\DataObjects\HttpFilterConfig;

/**
 * Resolve per-class HTTP filter config from #[TrackHttp] attribute or TracksOutboundHttp trait.
 *
 * Shared by RecordJobAudit, RecordCommandAudit, and RecordEventAudit listeners.
 * Priority: attribute > trait > null.
 */
trait ResolvesHttpFilterConfig
{
    /**
     * Build an HttpFilterConfig from the #[TrackHttp] attribute or TracksOutboundHttp trait on a class.
     *
     * @param  class-string  $className  The fully qualified class name to resolve config from.
     */
    private function resolveHttpFilterConfig(string $className): ?HttpFilterConfig
    {
        if (! class_exists($className)) {
            return null;
        }

        $ref = new \ReflectionClass($className);
        $attrs = $ref->getAttributes(TrackHttp::class);

        if ($attrs) {
            $attr = $attrs[0]->newInstance();

            return new HttpFilterConfig(
                enabled: $attr->enabled,
                includeHosts: $attr->includeHosts,
                excludeHosts: $attr->excludeHosts,
            );
        }

        if (in_array(TracksOutboundHttp::class, class_uses_recursive($className), true)) {
            $instance = $ref->newInstanceWithoutConstructor();

            return new HttpFilterConfig(
                enabled: $instance->shouldTrackHttp(),
                includeHosts: $instance->httpIncludeHosts(),
                excludeHosts: $instance->httpExcludeHosts(),
            );
        }

        return null;
    }
}
