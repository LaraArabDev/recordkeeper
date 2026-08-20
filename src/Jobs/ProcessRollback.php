<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Queue\InteractsWithQueue;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\Rollback;

/**
 * Queued job to revert a single audit asynchronously.
 */
final class ProcessRollback implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    /**
     * Create a new ProcessRollback job instance.
     *
     * @param  int  $auditId  The ID of the audit record to revert.
     * @param  bool  $recordTrail  Whether to record a trail audit for the rollback.
     */
    public function __construct(
        private readonly int $auditId,
        private readonly bool $recordTrail = true,
    ) {}

    /**
     * Execute the rollback job.
     *
     * @param  Rollback  $rollback  The rollback service instance.
     *
     * @throws ModelNotFoundException When the audit record is not found.
     */
    public function handle(Rollback $rollback): void
    {
        $audit = Audit::findOrFail($this->auditId);

        $rollback->revert($audit, false, $this->recordTrail);
    }
}
