<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Dispatched after a single audit has been successfully reverted.
 */
final class RollbackCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly Audit $audit,
        public readonly mixed $result,
    ) {}
}
