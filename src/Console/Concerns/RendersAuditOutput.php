<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console\Concerns;

use LaraArabDev\Recordkeeper\Support\TerminalRenderer;

/**
 * Shared format validation and audit row rendering for console commands.
 */
trait RendersAuditOutput
{
    /**
     * Validate the --format option against allowed values.
     *
     * @param  string  $format  The format string to validate.
     * @param  list<string>  $allowed  The list of allowed format values.
     * @return bool True if the format is valid.
     */
    protected function validateFormat(string $format, array $allowed = ['table', 'json', 'csv']): bool
    {
        if (in_array($format, $allowed, true)) {
            return true;
        }

        $this->error("Invalid format '{$format}'. Allowed: ".implode(', ', $allowed).'.');

        return false;
    }

    /**
     * Render audit rows in the given format.
     *
     * @param  list<array<string, scalar>>  $rows  The audit data rows to render.
     * @param  string  $format  The output format (table, json, or csv).
     */
    protected function renderRows(array $rows, string $format): void
    {
        match ($format) {
            'json' => TerminalRenderer::json($rows),
            'csv' => TerminalRenderer::csv(array_keys($rows[0]), $rows),
            default => TerminalRenderer::table(array_keys($rows[0]), $rows),
        };
    }

    /**
     * Output the appropriate empty-state message for the given format.
     *
     * @param  string  $format  The output format (table, json, or csv).
     * @param  string  $message  The message to display for non-JSON formats.
     */
    protected function renderEmpty(string $format, string $message = 'No audit records found.'): void
    {
        $format === 'json' ? $this->line('[]') : $this->warn($message);
    }
}
