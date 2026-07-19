<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AuditQuery;
use LaraArabDev\Recordkeeper\Support\TerminalRenderer;

/**
 * Show the full change timeline for a specific model instance.
 */
class HistoryCommand extends Command
{
    protected $signature = 'recordkeeper:history
        {model : Model class name (e.g. Order or App\\Models\\Order)}
        {id : Model instance ID}
        {--format=table : Output format (table|json)}
        {--limit=50 : Maximum number of records}';

    protected $description = 'Show change history for a specific model instance';

    public function handle(): int
    {
        $model = (string) $this->argument('model');
        $id = (string) $this->argument('id');
        $limit = (int) $this->option('limit');

        $audits = (new AuditQuery)
            ->model($model)
            ->subjectId($id)
            ->latest()
            ->limit($limit)
            ->builder()
            ->get();

        if ($audits->isEmpty()) {
            $this->warn("No audit history found for {$model} #{$id}.");

            return self::SUCCESS;
        }

        if ($this->option('format') === 'json') {
            TerminalRenderer::json(TerminalRenderer::mapToRows($audits));

            return self::SUCCESS;
        }

        $this->renderTimeline($audits, $model, $id);

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Audit>  $audits
     */
    private function renderTimeline(Collection $audits, string $model, string $id): void
    {
        $this->info("History for {$model} #{$id} ({$audits->count()} records):");
        $this->newLine();

        foreach ($audits as $audit) {
            /** @var Audit $audit */
            $actor = $audit->user_id
                ? class_basename($audit->user_type ?? 'User').' #'.$audit->user_id
                : 'system';
            $this->line(sprintf(
                '  <comment>[%s]</comment> <info>%s</info> by %s',
                $audit->created_at?->format('Y-m-d H:i:s') ?? '',
                $audit->event,
                $actor,
            ));
            TerminalRenderer::diff($audit);
            $this->newLine();
        }
    }
}
