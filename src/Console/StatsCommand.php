<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Actions\GatherAuditStats;
use LaraArabDev\Recordkeeper\Support\TerminalRenderer;

/**
 * Display an audit statistics dashboard with event, model, and actor breakdowns.
 */
class StatsCommand extends Command
{
    protected $signature = 'recordkeeper:stats
        {--since= : From date (e.g. "-30 days")}
        {--json : Output as JSON}';

    protected $description = 'Show audit statistics dashboard';

    /**
     * Execute the stats command and display the audit statistics dashboard.
     *
     * @param  GatherAuditStats  $gather  The stats gathering action.
     * @return int The command exit code.
     */
    public function handle(GatherAuditStats $gather): int
    {
        $stats = $gather($this->option('since'));

        if ($this->option('json')) {
            TerminalRenderer::json($stats);

            return self::SUCCESS;
        }

        $this->renderDashboard($stats);

        return self::SUCCESS;
    }

    /**
     * Render the statistics dashboard to the terminal.
     *
     * @param  array<string, mixed>  $stats  The gathered statistics data.
     */
    private function renderDashboard(array $stats): void
    {
        $this->newLine();
        $sinceText = $stats['since'] ? " (since {$stats['since']->toDateString()})" : '';
        $this->line("  <info>Total audits:</info> {$stats['total']}{$sinceText}");
        $this->newLine();

        $this->line('  <comment>By event:</comment>');
        $eventRows = collect($stats['by_event'])->map(fn ($count, $event) => compact('event', 'count'))->all();
        TerminalRenderer::table(['event', 'count'], $eventRows);
        $this->newLine();

        $this->line('  <comment>Top models:</comment>');
        TerminalRenderer::table(['model', 'count'], $stats['top_models']);
        $this->newLine();

        $this->line('  <comment>Top actors:</comment>');
        TerminalRenderer::table(['actor', 'count'], $stats['top_actors']);
        $this->newLine();
    }
}
