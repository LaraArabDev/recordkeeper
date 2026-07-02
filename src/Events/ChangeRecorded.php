<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Dispatched after an audit record has been successfully persisted.
 *
 * Consumers can listen to this event for post-audit side effects
 * such as notifications, webhooks, or cache invalidation.
 */
final class ChangeRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly Audit $audit,
    ) {}
}
