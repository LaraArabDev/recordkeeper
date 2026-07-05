<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Dispatched before a rollback operation begins.
 */
final class RollbackStarted
{
    use Dispatchable;

    public function __construct(
        public readonly Audit $audit,
        public readonly bool $dryRun,
    ) {}
}
