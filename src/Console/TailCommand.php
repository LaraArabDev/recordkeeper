<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use LaraArabDev\Recordkeeper\Actions\TailAudits;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\TerminalRenderer;

/**
 * Live-follow new audit records in real time, similar to `tail -f`.
 */
class TailCommand extends Command
{
    protected $signature = 'recordkeeper:tail
        {--model= : Filter by model class name}
        {--event= : Filter by event}
        {--guard= : Filter by guard}
        {--interval=3 : Poll interval in seconds}
        {--json : Stream NDJSON}';

    protected $description = 'Live-follow audit records (like tail -f)';

    /**
     * Poll for new audit records in a loop and render them as they arrive.
     *
     * @param  TailAudits  $tailer  The tail audits action.
     * @return int The command exit code.
     */
    public function handle(TailAudits $tailer): int
    {
        $lastId = $tailer->latestId();
        $interval = max(1, (int) $this->option('interval'));
        $json = (bool) $this->option('json');

        if (! $json) {
            $this->line('Watching for new audit records... (Ctrl-C to stop)');
            $this->newLine();
        }

        while (true) {
            $audits = $tailer->poll(
                $lastId,
                $this->option('model'),
                $this->option('event'),
                $this->option('guard'),
            );

            foreach ($audits as $audit) {
                $lastId = max($lastId, (int) $audit->id);
                $json
                    ? $this->renderJson($audit)
                    : $this->renderLine($audit);
            }

            sleep($interval);
        }

        return self::SUCCESS; // @codeCoverageIgnore
    }

    /**
     * Render a single audit as a formatted terminal line.
     *
     * @param  Audit  $audit  The audit record to render.
     */
    private function renderLine(Audit $audit): void
    {
        $time = $audit->created_at?->format('H:i:s') ?? '';
        $event = str_pad($audit->event, 20);
        $subject = class_basename((string) $audit->auditable_type).' #'.$audit->auditable_id;
        $actor = $audit->user_id ? "User #{$audit->user_id}" : 'system';
        $changed = implode(', ', array_keys($audit->getModified() ?? []));

        $this->line("{$time}  {$event}  {$subject}  {$actor}  {$changed}");
    }

    /**
     * Render a single audit as NDJSON.
     *
     * @param  Audit  $audit  The audit record to render.
     */
    private function renderJson(Audit $audit): void
    {
        $this->line(json_encode(TerminalRenderer::auditToRow($audit)));
    }
}
