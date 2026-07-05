<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Listeners;

use LaraArabDev\Recordkeeper\Actions\RecordAudit;
use LaraArabDev\Recordkeeper\Attributes\AuditEvent;
use LaraArabDev\Recordkeeper\Concerns\AuditsEvent;
use LaraArabDev\Recordkeeper\Concerns\ResolvesHttpFilterConfig;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Support\HttpTracker;

/**
 * Listen to all application events and record audits for opted-in events.
 *
 * Events opt in via the {@see AuditEvent} attribute or the `recordkeeper.listen` config array.
 * Framework events (Illuminate, Laravel, eloquent) are automatically skipped.
 */
final class RecordEventAudit
{
    use ResolvesHttpFilterConfig;

    public function __construct(private readonly RecordAudit $recordAudit) {}

    /**
     * Handle a wildcard event and record an audit if the event is opted in.
     *
     * @param  array<mixed>  $payload
     */
    public function handle(string $eventName, array $payload): void
    {
        if (! config('recordkeeper.enabled', true)) {
            return;
        }

        if (str_starts_with($eventName, 'Illuminate\\')
            || str_starts_with($eventName, 'Laravel\\')
            || str_starts_with($eventName, 'eloquent.')
        ) {
            return;
        }

        $eventClass = $eventName;

        if (! $this->shouldAudit($eventClass)) {
            return;
        }

        $attr = $this->attribute($eventClass);
        $eventInstance = $payload[0] ?? null;

        // Set HTTP tracking context before audit write (events are synchronous)
        $httpEnabled = config('recordkeeper.http.enabled', false);
        if ($httpEnabled) {
            $filterConfig = $this->resolveHttpFilterConfig($eventClass);
            app(HttpTracker::class)->setContext(0, $filterConfig);
        }

        $context = ['event' => $eventClass];

        $capturePayload = $this->resolveCapturePayload($eventInstance, $attr);
        if ($capturePayload) {
            $context['payload'] = $this->serializePayload($payload);
        }

        $tags = $this->resolveTags($eventInstance, $attr);

        ($this->recordAudit)(new AuditPayload(
            event: 'event.'.class_basename($eventClass),
            auditableType: 'event',
            auditableId: null,
            oldValues: [],
            newValues: [],
            tags: implode(',', $tags),
            context: $context,
            source: $eventClass,
        ));

        if ($httpEnabled) {
            app(HttpTracker::class)->clearContext();
        }
    }

    /**
     * Determine whether the given event class should be audited.
     *
     * @param  class-string  $eventClass
     */
    private function shouldAudit(string $eventClass): bool
    {
        $excluded = config('recordkeeper.events_tracking.exclude', []);
        if (in_array($eventClass, $excluded, true)) {
            return false;
        }

        if (config('recordkeeper.events_tracking.enabled', false)) {
            return true;
        }

        $inConfig = in_array($eventClass, config('recordkeeper.listen', []), true);

        return $this->attribute($eventClass) !== null || $this->usesTrait($eventClass) || $inConfig;
    }

    /**
     * Resolve tags from attribute, trait, or empty default.
     *
     * Priority: attribute > trait > empty.
     *
     * @return list<string>
     */
    private function resolveTags(mixed $eventInstance, ?AuditEvent $attr): array
    {
        if ($attr !== null) {
            return $attr->tags;
        }

        if (is_object($eventInstance) && method_exists($eventInstance, 'auditEventTags')) {
            return $eventInstance->auditEventTags();
        }

        return [];
    }

    /**
     * Resolve capturePayload from attribute, trait, or default false.
     *
     * Priority: attribute > trait > false.
     */
    private function resolveCapturePayload(mixed $eventInstance, ?AuditEvent $attr): bool
    {
        if ($attr !== null) {
            return $attr->capturePayload;
        }

        if (is_object($eventInstance) && method_exists($eventInstance, 'shouldCapturePayload')) {
            return $eventInstance->shouldCapturePayload();
        }

        return false;
    }

    /**
     * Check whether the given class uses the AuditsEvent trait.
     *
     * @param  class-string  $eventClass
     */
    private function usesTrait(string $eventClass): bool
    {
        if (! class_exists($eventClass)) {
            return false;
        }

        return in_array(AuditsEvent::class, class_uses_recursive($eventClass), true);
    }

    /**
     * Retrieve the #[AuditEvent] attribute instance from an event class, if present.
     *
     * @param  class-string  $eventClass
     */
    private function attribute(string $eventClass): ?AuditEvent
    {
        if (! class_exists($eventClass)) {
            return null;
        }

        $attrs = (new \ReflectionClass($eventClass))->getAttributes(AuditEvent::class);

        return $attrs ? $attrs[0]->newInstance() : null;
    }

    /**
     * Safely serialize event payload objects to arrays for storage.
     *
     * @param  list<mixed>  $payload
     * @return list<mixed>
     */
    private function serializePayload(array $payload): array
    {
        $result = [];

        foreach ($payload as $item) {
            if (is_object($item)) {
                if (method_exists($item, 'toArray')) {
                    $result[] = $item->toArray();
                } else {
                    $result[] = get_object_vars($item);
                }
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }
}
