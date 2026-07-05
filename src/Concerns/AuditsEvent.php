<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Concerns;

/**
 * Opt an application event class into audit tracking by adding this trait.
 *
 * Override any method to customize behavior per class.
 * When both this trait and the #[AuditEvent] attribute are present, the attribute wins.
 */
trait AuditsEvent
{
    /**
     * Tags to attach to the audit entry for this event.
     *
     * @return list<string>
     */
    public function auditEventTags(): array
    {
        return [];
    }

    /**
     * Whether to capture the event payload in the audit context.
     */
    public function shouldCapturePayload(): bool
    {
        return false;
    }
}
