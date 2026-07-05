<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Actions\UndoChanges;
use LaraArabDev\Recordkeeper\Console\Concerns\ConfirmsAndExecutes;
use LaraArabDev\Recordkeeper\Support\TerminalRenderer;

/**
 * Quick undo of the last N rollbackable changes (like git undo).
 */
class UndoCommand extends Command
{
    use ConfirmsAndExecutes;

    protected $signature = 'recordkeeper:undo
        {n=1 : Number of changes to undo}
        {--model= : Scope undo to a specific model type}
        {--dry-run : Preview changes without applying}
        {--yes : Skip confirmation prompt}
        {--async : Dispatch undo to queue}';

    protected $description = 'Undo the last N rollbackable changes';

    /**
     * Fetch the last N rollbackable audits, preview them, and revert on confirmation.
     */
    public function handle(UndoChanges $undo): int
    {
        $audits = $undo->findUndoable((int) $this->argument('n'), $this->option('model'));

        if ($audits->isEmpty()) {
            $this->warn('No rollbackable audits found.');

            return self::SUCCESS;
        }

        TerminalRenderer::table(
            array_keys(TerminalRenderer::auditToRow($audits->first())),
            $audits->map(fn ($a) => TerminalRenderer::auditToRow($a))->all(),
        );

        return $this->confirmAndExecute(
            confirmMessage: "Undo {$audits->count()} change(s)?",
            dryRunMessage: 'Dry-run — no changes applied.',
            onSync: function () use ($undo, $audits): int {
                $results = $undo->revert($audits);
                $this->info('Undid '.count($results).' change(s) successfully.');

                return self::SUCCESS;
            },
            onAsync: fn () => $undo->revertAsync($audits),
            asyncMessage: 'Undo job dispatched to queue.',
        );
    }
}
