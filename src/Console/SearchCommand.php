<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Console\Concerns\BuildsAuditFilters;
use LaraArabDev\Recordkeeper\Support\TerminalRenderer;

/**
 * Search and filter audit records with tabular, JSON, or CSV output.
 */
class SearchCommand extends Command
{
    use BuildsAuditFilters;

    protected $signature = 'recordkeeper:search
        {--model= : Filter by model class name}
        {--event=* : Filter by event (repeatable)}
        {--user= : Filter by actor ID (the authenticated user who performed the action)}
        {--guard= : Filter by auth guard name (e.g. web, api, admin)}
        {--tag= : Filter by tag}
        {--batch= : Filter by batch ID}
        {--since= : From date (e.g. "-7 days")}
        {--until= : Until date}
        {--q= : Free-text search}
        {--limit=25 : Number of results}
        {--page=1 : Page number}
        {--json : Output as JSON}
        {--format=table : Output format (table|json|csv)}';

    protected $description = 'Search audit records';

    /**
     * Build filters from CLI options and render results.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $page = max(1, (int) $this->option('page'));
        $format = $this->option('json') ? 'json' : $this->option('format');

        $audits = $this->buildAuditQuery()
            ->latest()
            ->limit($limit)
            ->offset(($page - 1) * $limit)
            ->builder()
            ->get();

        if ($audits->isEmpty()) {
            $format === 'json' ? $this->line('[]') : $this->warn('No audit records found.');

            return self::SUCCESS;
        }

        $rows = $audits->map(fn ($a) => TerminalRenderer::auditToRow($a))->all();

        match ($format) {
            'json' => TerminalRenderer::json($rows),
            'csv' => TerminalRenderer::csv(array_keys($rows[0]), $rows),
            default => TerminalRenderer::table(array_keys($rows[0]), $rows),
        };

        return self::SUCCESS;
    }
}
