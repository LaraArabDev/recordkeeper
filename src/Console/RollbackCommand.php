<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Actions\RollbackAudits;
use LaraArabDev\Recordkeeper\Console\Concerns\BuildsAuditFilters;
use LaraArabDev\Recordkeeper\Console\Concerns\ConfirmsAndExecutes;
use LaraArabDev\Recordkeeper\Support\TerminalRenderer;

/**
 * Revert a single audit, a batch, or audits matching filters.
 */
class RollbackCommand extends Command
{
    use BuildsAuditFilters;
    use ConfirmsAndExecutes;

    protected $signature = 'recordkeeper:rollback
        {id? : Audit record ID to revert}
        {--batch= : Revert all audits in a batch}
        {--tag= : Rollback all audits matching a tag}
        {--model= : Rollback all audits for a model type}
        {--model-id= : Rollback audits for a specific model instance (requires --model)}
        {--event=* : Filter by event type}
        {--since= : Filter audits created after this date}
        {--until= : Filter audits created before this date}
        {--dry-run : Preview changes without applying}
        {--yes : Skip confirmation prompt}
        {--async : Dispatch rollback to queue}';

    protected $description = 'Revert one audit, a batch, or audits matching filters';

    public function handle(RollbackAudits $rollback): int
    {
        if ($this->option('model-id') && ! $this->option('model')) {
            $this->error('The --model-id option requires --model to be specified.');

            return self::FAILURE;
        }

        if ($this->option('batch')) {
            return $this->handleBatch($rollback);
        }

        if ($this->hasAnyFilter()) {
            return $this->handleFiltered($rollback);
        }

        return $this->handleSingle($rollback);
    }

    private function handleSingle(RollbackAudits $rollback): int
    {
        $id = $this->argument('id');

        if ($id === null) {
            $this->error('Provide an audit ID, --batch=<id>, or filter options.');

            return self::FAILURE;
        }

        $audit = $rollback->findById($id);

        if ($audit === null) {
            $this->error("Audit #{$id} not found.");

            return self::FAILURE;
        }

        if (! $audit->isRollbackable()) {
            $this->error("Audit #{$id} (event: {$audit->event}) cannot be rolled back.");

            return self::FAILURE;
        }

        $preview = $rollback->preview($audit);
        $this->line('  <comment>Dry-run preview:</comment>');
        $this->line('  Action: '.($preview['action'] ?? 'update'));
        TerminalRenderer::diff($audit);

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        return $this->confirmAndExecute(
            confirmMessage: "Apply rollback for Audit #{$id}?",
            dryRunMessage: '',
            onSync: function () use ($rollback, $audit, $id): int {
                $rollback->revert($audit);
                $this->info("Audit #{$id} rolled back successfully.");

                return self::SUCCESS;
            },
            onAsync: fn () => $rollback->revertAsync($audit),
            asyncMessage: 'Rollback job dispatched to queue.',
        );
    }

    private function handleBatch(RollbackAudits $rollback): int
    {
        $batchId = (string) $this->option('batch');

        if ($this->option('async') && ! $this->option('dry-run')) {
            $audits = $rollback->findByBatch($batchId);

            if ($audits->isEmpty()) {
                $this->warn("No rollbackable audits found for batch: {$batchId}");

                return self::SUCCESS;
            }

            $rollback->revertCollectionAsync($audits);
            $this->info('Rollback job dispatched to queue.');

            return self::SUCCESS;
        }

        $results = $rollback->revertBatch($batchId, (bool) $this->option('dry-run'));

        if (empty($results)) {
            $this->warn("No rollbackable audits found for batch: {$batchId}");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry-run — '.count($results).' audit(s) would be reverted in batch: '.$batchId);

            return self::SUCCESS;
        }

        $this->info('Rolled back '.count($results).' audit(s) in batch: '.$batchId);

        return self::SUCCESS;
    }

    private function handleFiltered(RollbackAudits $rollback): int
    {
        $audits = $rollback->findByQuery($this->buildAuditQuery());

        if ($audits->isEmpty()) {
            $this->warn('No rollbackable audits found matching the given filters.');

            return self::SUCCESS;
        }

        TerminalRenderer::table(
            array_keys(TerminalRenderer::auditToRow($audits->first())),
            $audits->map(fn ($a) => TerminalRenderer::auditToRow($a))->all(),
        );

        return $this->confirmAndExecute(
            confirmMessage: "Rollback {$audits->count()} audit(s)?",
            dryRunMessage: 'Dry-run — no changes applied.',
            onSync: function () use ($rollback, $audits): int {
                $results = $rollback->revertCollection($audits);
                $this->info('Rolled back '.count($results).' audit(s) successfully.');

                return self::SUCCESS;
            },
            onAsync: fn () => $rollback->revertCollectionAsync($audits),
            asyncMessage: 'Rollback job dispatched to queue.',
        );
    }
}
