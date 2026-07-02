<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\Rollback;
use LaraArabDev\Recordkeeper\Support\TerminalRenderer;

/**
 * Revert a single audit or an entire batch with dry-run preview support.
 */
class RollbackCommand extends Command
{
    protected $signature = 'recordkeeper:rollback
        {id? : Audit record ID to revert}
        {--batch= : Revert all audits in a batch}
        {--dry-run : Preview changes without applying}
        {--yes : Skip confirmation prompt}';

    protected $description = 'Revert one audit or an entire batch';

    public function __construct(
        private readonly Rollback $rollback,
    ) {
        parent::__construct();
    }

    /**
     * Validate input, show a dry-run preview, then apply the rollback on confirmation.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($batch = $this->option('batch')) {
            return $this->handleBatch((string) $batch, $dryRun);
        }

        $id = $this->argument('id');

        if ($id === null) {
            $this->error('Please provide an audit ID or --batch=<id>.');

            return self::FAILURE;
        }

        $audit = Audit::find($id);

        if ($audit === null) {
            $this->error("Audit #{$id} not found.");

            return self::FAILURE;
        }

        if (! $audit->isRollbackable()) {
            $this->error("Audit #{$id} (event: {$audit->event}) cannot be rolled back.");

            return self::FAILURE;
        }

        $preview = $this->rollback->revert($audit, true);
        $this->line('  <comment>Dry-run preview:</comment>');
        $this->line('  Action: '.($preview['action'] ?? 'update'));
        TerminalRenderer::diff($audit);

        if ($dryRun) {
            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm("Apply rollback for Audit #{$id}?")) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $this->rollback->revert($audit, false);
        $this->info("Audit #{$id} rolled back successfully.");

        return self::SUCCESS;
    }

    /**
     * Revert all rollbackable audits in a given batch.
     */
    private function handleBatch(string $batchId, bool $dryRun): int
    {
        $results = $this->rollback->revertBatch($batchId, $dryRun);

        if (empty($results)) {
            $this->warn("No rollbackable audits found for batch: {$batchId}");

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('Dry-run — '.count($results).' audit(s) would be reverted in batch: '.$batchId);

            return self::SUCCESS;
        }

        $this->info('Rolled back '.count($results).' audit(s) in batch: '.$batchId);

        return self::SUCCESS;
    }
}
