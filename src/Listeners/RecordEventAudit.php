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
 * Framework events (Illuminate, Laravel, eloquent) and internal Recordkeeper events are automatically skipped.
 */
final class RecordEventAudit
{
    use ResolvesHttpFilterConfig;

    public function __construct(private readonly RecordAudit $recordAudit) {}

    /**
     * Handle a wildcard event and record an audit if the event is opted in.
     *
     * @param  string  $eventName  The fully qualified event class name or string identifier.
     * @param  array<mixed>  $payload  The event payload passed by the dispatcher.
     */
    public function handle(string $eventName, array $payload): void
    {
        if (! config('recordkeeper.enabled', true)) {
            return;
        }

        if ($this->isFrameworkEvent($eventName)) {
            return;
        }

        $attr = $this->attribute($eventName);

        if (! $this->shouldAudit($eventName, $attr)) {
            return;
        }

        $eventInstance = $payload[0] ?? null;

        // Set HTTP tracking context before audit write (events are synchronous)
        $httpEnabled = config('recordkeeper.http.enabled', false);
        if ($httpEnabled) {
            $filterConfig = $this->resolveHttpFilterConfig($eventName);
            app(HttpTracker::class)->setContext(0, $filterConfig);
        }

        $context = ['event' => $eventName];

        $capturePayload = $this->resolveCapturePayload($eventInstance, $attr);
        if ($capturePayload) {
            $context['payload'] = $this->serializePayload($payload);
        }

        $tags = $this->resolveTags($eventInstance, $attr);

        ($this->recordAudit)(new AuditPayload(
            event: 'event.'.class_basename($eventName),
            auditableType: 'event',
            auditableId: null,
            oldValues: [],
            newValues: [],
            tags: implode(',', $tags),
            context: $context,
            source: $eventName,
        ));

        if ($httpEnabled) {
            app(HttpTracker::class)->clearContext();
        }
    }

    /**
     * Check whether the event is a framework-internal event that should never be audited.
     *
     * @param  string  $eventName  The event class name or string identifier to check.
     */
    private function isFrameworkEvent(string $eventName): bool
    {
        return str_starts_with($eventName, 'Illuminate\\')
            || str_starts_with($eventName, 'Laravel\\')
            || str_starts_with($eventName, 'LaraArabDev\\Recordkeeper\\Events\\')
            || str_starts_with($eventName, 'eloquent.');
    }

    /**
     * Determine whether the given event class should be audited.
     *
     * @param  class-string  $eventName  The fully qualified event class name.
     * @param  AuditEvent|null  $attr  The resolved AuditEvent attribute, if present.
     */
    private function shouldAudit(string $eventName, ?AuditEvent $attr): bool
    {
        $excluded = config('recordkeeper.events_tracking.exclude', []);
        if (in_array($eventName, $excluded, true)) {
            return false;
        }

        if (config('recordkeeper.events_tracking.enabled', false)) {
            return true;
        }

        return $attr !== null
            || $this->usesTrait($eventName)
            || in_array($eventName, config('recordkeeper.listen', []), true);
    }

    /**
     * Resolve tags from attribute, trait, or empty default.
     *
     * Priority: attribute > trait > empty.
     *
     * @param  mixed  $eventInstance  The event object instance, or null if unavailable.
     * @param  AuditEvent|null  $attr  The resolved AuditEvent attribute, if present.
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
     *
     * @param  mixed  $eventInstance  The event object instance, or null if unavailable.
     * @param  AuditEvent|null  $attr  The resolved AuditEvent attribute, if present.
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
     * @param  class-string  $eventName  The fully qualified event class name.
     */
    private function usesTrait(string $eventName): bool
    {
        if (! class_exists($eventName)) {
            return false;
        }

        return in_array(AuditsEvent::class, class_uses_recursive($eventName), true);
    }

    /**
     * Retrieve the #[AuditEvent] attribute instance from an event class, if present.
     *
     * @param  class-string  $eventName  The fully qualified event class name.
     */
    private function attribute(string $eventName): ?AuditEvent
    {
        if (! class_exists($eventName)) {
            return null;
        }

        $attrs = (new \ReflectionClass($eventName))->getAttributes(AuditEvent::class);

        return $attrs ? $attrs[0]->newInstance() : null;
    }

    /**
     * Safely serialize event payload objects to arrays for storage.
     *
     * @param  list<mixed>  $payload  The raw event payload items to serialize.
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
