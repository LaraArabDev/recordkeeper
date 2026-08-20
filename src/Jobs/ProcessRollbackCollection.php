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
     * Create a new ProcessRollbackCollection job instance.
     *
     * @param  list<int>  $auditIds  The IDs of the audit records to revert.
     * @param  bool  $recordTrail  Whether to record trail audits for each rollback.
     */
    public function __construct(
        private readonly array $auditIds,
        private readonly bool $recordTrail = true,
    ) {}

    /**
     * Execute the collection rollback job.
     *
     * @param  Rollback  $rollback  The rollback service instance.
     */
    public function handle(Rollback $rollback): void
    {
        $audits = Audit::whereIn('id', $this->auditIds)
            ->with('auditable')
            ->get();

        $rollback->revertCollection($audits, false, $this->recordTrail);
    }
}
