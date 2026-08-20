<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Console\Concerns\ConfirmsAndExecutes;
use LaraArabDev\Recordkeeper\Support\AuditQuery;
use LaraArabDev\Recordkeeper\Support\Rollback;
use LaraArabDev\Recordkeeper\Support\TerminalRenderer;

/**
 * Restore a deleted model instance by reverting its most recent deletion audit.
 */
class RestoreCommand extends Command
{
    use ConfirmsAndExecutes;

    protected $signature = 'recordkeeper:restore
        {model : Model class name (e.g. Order or App\\Models\\Order)}
        {id : Model instance ID}
        {--dry-run : Preview changes without applying}
        {--yes : Skip confirmation prompt}
        {--async : Dispatch restore to queue}';

    protected $description = 'Restore a deleted model instance from its audit trail';

    /**
     * Find the latest deletion audit for the given model and offer to restore it.
     *
     * @param  Rollback  $rollback  The rollback service instance.
     * @return int The command exit code.
     */
    public function handle(Rollback $rollback): int
    {
        $model = (string) $this->argument('model');
        $id = (string) $this->argument('id');

        $audit = (new AuditQuery)
            ->model($model)
            ->subjectId($id)
            ->event(['deleted', 'forceDeleted'])
            ->latest()
            ->limit(1)
            ->builder()
            ->first();

        if ($audit === null) {
            $this->error("No deletion audit found for {$model} #{$id}.");

            return self::FAILURE;
        }

        TerminalRenderer::table(
            array_keys(TerminalRenderer::auditToRow($audit)),
            [TerminalRenderer::auditToRow($audit)],
        );
        TerminalRenderer::diff($audit);

        return $this->confirmAndExecute(
            confirmMessage: "Restore {$model} #{$id}?",
            dryRunMessage: 'Dry-run — no changes applied.',
            onSync: function () use ($rollback, $audit, $model, $id): int {
                $rollback->revert($audit);
                $this->info("{$model} #{$id} restored successfully.");

                return self::SUCCESS;
            },
            onAsync: fn () => $rollback->revertAsync($audit),
            asyncMessage: 'Restore job dispatched to queue.',
        );
    }
}
