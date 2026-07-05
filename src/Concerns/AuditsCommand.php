<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Concerns;

/**
 * Opt an Artisan command class into audit tracking by adding this trait.
 *
 * Override any method to customize behavior per class.
 * When both this trait and the #[AuditCommand] attribute are present, the attribute wins.
 *
 * WARNING: Do not reference constructor properties in these methods — the listener
 * may instantiate the class via ReflectionClass::newInstanceWithoutConstructor().
 */
trait AuditsCommand
{
    /**
     * Tags to attach to the audit entry for this command.
     *
     * @return list<string>
     */
    public function auditCommandTags(): array
    {
        return [];
    }
}
