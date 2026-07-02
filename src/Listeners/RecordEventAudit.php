<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Listeners;

use LaraArabDev\Recordkeeper\Actions\RecordAudit;
use LaraArabDev\Recordkeeper\Attributes\AuditEvent;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;

/**
 * Listen to all application events and record audits for opted-in events.
 *
 * Events opt in via the {@see AuditEvent} attribute or the `recordkeeper.listen` config array.
 * Framework events (Illuminate, Laravel, eloquent) are automatically skipped.
 */
final class RecordEventAudit
{
    public function __construct(private readonly RecordAudit $recordAudit) {}

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

        $attr = $this->attribute($eventClass);
        $inConfig = in_array($eventClass, config('recordkeeper.listen', []), true);

        if ($attr === null && ! $inConfig) {
            return;
        }

        $context = ['event' => $eventClass];

        if ($attr?->capturePayload) {
            $context['payload'] = $this->serializePayload($payload);
        }

        ($this->recordAudit)(new AuditPayload(
            event: 'event.'.class_basename($eventClass),
            auditableType: 'event',
            auditableId: null,
            oldValues: [],
            newValues: [],
            tags: implode(',', $attr?->tags ?? []),
            context: $context,
        ));
    }

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
