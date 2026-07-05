<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\Rollback;

/**
 * Queued job to revert a collection of audits asynchronously.
 */
final class ProcessRollbackCollection implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    /**
     * @param  list<int>  $auditIds
     */
    public function __construct(
        private readonly array $auditIds,
        private readonly bool $recordTrail = true,
    ) {}

    /**
     * Execute the collection rollback job.
     */
    public function handle(Rollback $rollback): void
    {
        $audits = Audit::whereIn('id', $this->auditIds)
            ->with('auditable')
            ->get();

        $rollback->revertCollection($audits, false, $this->recordTrail);
    }
}
