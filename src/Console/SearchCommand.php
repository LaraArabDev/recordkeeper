<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Console\Concerns\BuildsAuditFilters;
use LaraArabDev\Recordkeeper\Console\Concerns\RendersAuditOutput;
use LaraArabDev\Recordkeeper\Support\TerminalRenderer;

/**
 * Search and filter audit records with tabular, JSON, or CSV output.
 */
class SearchCommand extends Command
{
    use BuildsAuditFilters;
    use RendersAuditOutput;

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
        {--format=table : Output format (table|json|csv)}';

    protected $description = 'Search audit records';

    /**
     * Build filters from CLI options and render results.
     */
    public function handle(): int
    {
        $format = (string) $this->option('format');

        if (! $this->validateFormat($format)) {
            return self::FAILURE;
        }

        $audits = $this->buildAuditQuery()
            ->latest()
            ->paginate((int) $this->option('limit'), max(1, (int) $this->option('page')))
            ->builder()
            ->get();

        if ($audits->isEmpty()) {
            $this->renderEmpty($format);

            return self::SUCCESS;
        }

        $this->renderRows(TerminalRenderer::mapToRows($audits), $format);

        return self::SUCCESS;
    }
}
