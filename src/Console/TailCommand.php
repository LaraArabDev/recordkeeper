<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
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
     * Poll for new audit records and stream them to the console until interrupted.
     */
    public function handle(): int
    {
        $lastId = (int) (Audit::max('id') ?? 0);
        $interval = max(1, (int) $this->option('interval'));
        $json = (bool) $this->option('json');

        if (! $json) {
            $this->line('Watching for new audit records... (Ctrl-C to stop)');
            $this->newLine();
        }

        while (true) {
            $query = Audit::where('id', '>', $lastId)->orderBy('id');

            if ($this->option('model')) {
                $query->where('auditable_type', 'like', '%'.$this->option('model'));
            }
            if ($this->option('event')) {
                $query->where('event', $this->option('event'));
            }
            if ($this->option('guard')) {
                $query->whereRaw("JSON_EXTRACT(context, '$.guard') = ?", [$this->option('guard')]);
            }

            $audits = $query->get();

            foreach ($audits as $audit) {
                $lastId = max($lastId, (int) $audit->id);

                if ($json) {
                    echo json_encode(TerminalRenderer::auditToRow($audit))."\n";
                } else {
                    $time = $audit->created_at?->format('H:i:s') ?? '';
                    $event = str_pad($audit->event, 20);
                    $subject = class_basename((string) $audit->auditable_type).' #'.$audit->auditable_id;
                    $actor = $audit->user_id ? "User #{$audit->user_id}" : 'system';
                    $changed = implode(', ', array_keys($audit->getModified() ?? []));
                    $this->line("{$time}  {$event}  {$subject}  {$actor}  {$changed}");
                }
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }
}
