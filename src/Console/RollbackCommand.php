<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Console\Concerns\BuildsAuditFilters;
use LaraArabDev\Recordkeeper\Console\Concerns\ConfirmsAndExecutes;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\Rollback;
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

    /**
     * Execute the rollback command.
     *
     * @param  Rollback  $rollback  The rollback service instance.
     * @return int The command exit code.
     */
    public function handle(Rollback $rollback): int
    {
        if ($this->option('model-id') && ! $this->option('model')) {
            $this->error('The --model-id option requires --model to be specified.');

            return self::FAILURE;
        }

        return match (true) {
            (bool) $this->option('batch') => $this->handleBatch($rollback),
            $this->hasAnyFilter() => $this->handleFiltered($rollback),
            default => $this->handleSingle($rollback),
        };
    }

    /**
     * Handle rollback of a single audit record by ID.
     *
     * @param  Rollback  $rollback  The rollback service instance.
     * @return int The command exit code.
     */
    private function handleSingle(Rollback $rollback): int
    {
        $id = $this->argument('id');
        $audit = $id ? Audit::find($id) : null;

        if ($audit === null) {
            $this->error($id ? "Audit #{$id} not found." : 'Provide an audit ID, --batch=<id>, or filter options.');

            return self::FAILURE;
        }

        if (! $audit->isRollbackable()) {
            $this->error("Audit #{$id} (event: {$audit->event}) cannot be rolled back.");

            return self::FAILURE;
        }

        $preview = $rollback->revert($audit, true);
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

    /**
     * Handle rollback of all audits in a batch.
     *
     * @param  Rollback  $rollback  The rollback service instance.
     * @return int The command exit code.
     */
    private function handleBatch(Rollback $rollback): int
    {
        $batchId = (string) $this->option('batch');

        $count = Audit::where('batch_id', $batchId)
            ->whereIn('event', Audit::ROLLBACKABLE_EVENTS)
            ->count();

        if ($count === 0) {
            $this->warn("No rollbackable audits found for batch: {$batchId}");

            return self::SUCCESS;
        }

        $this->line("  <comment>{$count} audit(s) would be reverted in batch: {$batchId}</comment>");

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        return $this->confirmAndExecute(
            confirmMessage: "Rollback {$count} audit(s) in batch {$batchId}?",
            dryRunMessage: '',
            onSync: function () use ($rollback, $batchId, $count): int {
                $rollback->revertBatch($batchId);
                $this->info("Rolled back {$count} audit(s) in batch: {$batchId}");

                return self::SUCCESS;
            },
            onAsync: function () use ($rollback, $batchId): void {
                $audits = Audit::where('batch_id', $batchId)
                    ->whereIn('event', Audit::ROLLBACKABLE_EVENTS)
                    ->get();

                $rollback->revertCollectionAsync($audits);
            },
            asyncMessage: 'Rollback job dispatched to queue.',
        );
    }

    /**
     * Handle rollback of audits matching the provided filter options.
     *
     * @param  Rollback  $rollback  The rollback service instance.
     * @return int The command exit code.
     */
    private function handleFiltered(Rollback $rollback): int
    {
        $audits = $this->buildAuditQuery()
            ->rollbackable()
            ->latest()
            ->builder()
            ->get();

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
