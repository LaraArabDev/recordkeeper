<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console\Concerns;

/**
 * Extracts the repeated dry-run / confirm / async / sync flow
 * found in several console commands into a single reusable method.
 */
trait ConfirmsAndExecutes
{
    /**
     * Handle the dry-run / confirm / async / sync execution flow.
     *
     * @param  string  $confirmMessage  Prompt shown to the user before executing.
     * @param  string  $dryRunMessage  Message displayed when --dry-run is active.
     * @param  callable(): int  $onSync  Callback executed synchronously on confirmation.
     * @param  (callable(): void)|null  $onAsync  Callback dispatched to the queue when --async is set.
     * @param  string  $asyncMessage  Info message displayed after async dispatch.
     */
    protected function confirmAndExecute(
        string $confirmMessage,
        string $dryRunMessage,
        callable $onSync,
        ?callable $onAsync = null,
        string $asyncMessage = 'Job dispatched to queue.',
    ): int {
        if ($this->option('dry-run')) {
            $this->line("<comment>{$dryRunMessage}</comment>");

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm($confirmMessage)) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        if ($onAsync !== null && $this->option('async')) {
            $onAsync();
            $this->info($asyncMessage);

            return self::SUCCESS;
        }

        return $onSync();
    }
}
