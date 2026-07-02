<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\TerminalRenderer;

/**
 * Display a single audit record with a color-coded before/after diff.
 */
class ShowCommand extends Command
{
    protected $signature = 'recordkeeper:show {id : Audit record ID} {--json : Output as JSON}';

    protected $description = 'Show a single audit record with before/after diff';

    /**
     * Fetch a single audit by ID and render its details with a color-coded diff.
     */
    public function handle(): int
    {
        $id = $this->argument('id');
        $audit = Audit::find($id);

        if ($audit === null) {
            $this->error("Audit #{$id} not found.");

            return self::FAILURE;
        }

        if ($this->option('json')) {
            TerminalRenderer::json($audit->toArray());

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("  <comment>Audit #{$audit->id}</comment>");
        $this->line("  Event:   <info>{$audit->event}</info>");
        $this->line('  Subject: '.class_basename($audit->auditable_type)." #{$audit->auditable_id}");
        $this->line('  Actor:   '.($audit->user_id ? "{$audit->user_type} #{$audit->user_id}" : 'system'));
        $this->line('  Guard:   '.($audit->context['guard'] ?? 'n/a'));
        $this->line('  Batch:   '.($audit->batch_id ?? 'n/a'));
        $this->line('  Time:    '.$audit->created_at?->toIso8601String());
        $this->newLine();
        $this->line('  <comment>Changes:</comment>');
        TerminalRenderer::diff($audit);

        if (! empty($audit->context)) {
            $this->newLine();
            $this->line('  <comment>Context:</comment>');
            foreach ($audit->context as $k => $v) {
                $this->line("    {$k}: ".(is_array($v) ? json_encode($v) : $v));
            }
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
