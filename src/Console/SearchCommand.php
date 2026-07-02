<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Actions\SearchAudits;
use LaraArabDev\Recordkeeper\Support\TerminalRenderer;

/**
 * Search and filter audit records with tabular, JSON, or CSV output.
 */
class SearchCommand extends Command
{
    protected $signature = 'recordkeeper:search
        {--model= : Filter by model class name}
        {--event=* : Filter by event (repeatable)}
        {--user= : Filter by user ID}
        {--guard= : Filter by guard (web|api)}
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

    public function __construct(
        private readonly SearchAudits $searchAudits,
    ) {
        parent::__construct();
    }

    /**
     * Build filters from CLI options, execute the search, and render results.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $page = max(1, (int) $this->option('page'));
        $offset = ($page - 1) * $limit;

        $filters = array_filter([
            'model' => $this->option('model'),
            'event' => $this->option('event') ?: null,
            'user' => $this->option('user'),
            'guard' => $this->option('guard'),
            'tag' => $this->option('tag'),
            'batch' => $this->option('batch'),
            'since' => $this->option('since') ? strtotime((string) $this->option('since')) !== false
                ? date('Y-m-d H:i:s', strtotime((string) $this->option('since')))
                : $this->option('since') : null,
            'until' => $this->option('until'),
            'q' => $this->option('q'),
        ]);

        $audits = ($this->searchAudits)($filters, $limit, $offset);

        $format = $this->option('json') ? 'json' : $this->option('format');

        if ($audits->isEmpty()) {
            if ($format === 'json') {
                $this->line('[]');
            } else {
                $this->warn('No audit records found.');
            }

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
