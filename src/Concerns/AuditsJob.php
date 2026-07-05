<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Concerns;

/**
 * Opt a job class into audit tracking by adding this trait.
 *
 * Override any method to customize behavior per class.
 * When both this trait and the #[AuditJob] attribute are present, the attribute wins.
 *
 * WARNING: Do not reference constructor properties in these methods — the listener
 * may instantiate the class via ReflectionClass::newInstanceWithoutConstructor().
 */
trait AuditsJob
{
    /**
     * Tags to attach to every audit entry for this job.
     *
     * @return list<string>
     */
    public function auditJobTags(): array
    {
        return [];
    }

    /**
     * Whether to record an audit when this job is queued.
     */
    public function shouldAuditQueued(): bool
    {
        return true;
    }

    /**
     * Whether to record an audit when this job starts processing.
     */
    public function shouldAuditProcessing(): bool
    {
        return true;
    }

    /**
     * Whether to record an audit when this job finishes processing.
     */
    public function shouldAuditProcessed(): bool
    {
        return true;
    }

    /**
     * Whether to record an audit when this job fails.
     */
    public function shouldAuditFailed(): bool
    {
        return true;
    }
}
