<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Actions\ExportAudits;
use LaraArabDev\Recordkeeper\Console\Concerns\BuildsAuditFilters;

/**
 * Export audit records to a file in JSON, CSV, or NDJSON format.
 */
class ExportCommand extends Command
{
    use BuildsAuditFilters;

    protected $signature = 'recordkeeper:export
        {file : Output file path}
        {--format=json : Output format (json|csv|ndjson)}
        {--model= : Filter by model class name}
        {--model-id= : Filter by model instance ID}
        {--event=* : Filter by event type}
        {--tag= : Filter by tag}
        {--since= : From date}
        {--until= : Until date}
        {--batch= : Filter by batch ID}
        {--user= : Filter by actor ID (the authenticated user who performed the action)}
        {--guard= : Filter by auth guard name (e.g. web, api, admin)}
        {--q= : Free-text search}';

    protected $description = 'Export audit records to a file';

    /**
     * Build a filtered query and export matching audits to the given file.
     */
    public function handle(ExportAudits $exporter): int
    {
        $file = (string) $this->argument('file');
        $format = (string) $this->option('format');

        if (! in_array($format, ['json', 'csv', 'ndjson'], true)) {
            $this->error("Unsupported format: {$format}. Use json, csv, or ndjson.");

            return self::FAILURE;
        }

        $handle = @fopen($file, 'w');

        if ($handle === false) {
            $this->error("Cannot open file for writing: {$file}");

            return self::FAILURE;
        }

        $total = $exporter(
            $this->buildAuditQuery()->latest()->builder(),
            $handle,
            $format,
        );

        $this->info("Exported {$total} audit(s) to {$file} ({$format}).");

        return self::SUCCESS;
    }
}
