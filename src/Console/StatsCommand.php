<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LaraArabDev\Recordkeeper\Models\Audit;
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
     * Aggregate audit counts by event, model, and actor, then render a dashboard.
     */
    public function handle(): int
    {
        $since = null;
        if ($this->option('since')) {
            $ts = strtotime((string) $this->option('since'));
            $since = $ts !== false ? Carbon::createFromTimestamp($ts) : null;
        }

        $baseQuery = Audit::query();
        if ($since) {
            $baseQuery->where('created_at', '>=', $since);
        }

        $total = (clone $baseQuery)->count();

        $byEvent = (clone $baseQuery)
            ->select('event', DB::raw('COUNT(*) as count'))
            ->groupBy('event')
            ->orderByDesc('count')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->event => $r->count])
            ->all();

        $topModels = (clone $baseQuery)
            ->select('auditable_type', DB::raw('COUNT(*) as count'))
            ->groupBy('auditable_type')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['model' => class_basename($r->auditable_type), 'count' => $r->count])
            ->all();

        $topActors = (clone $baseQuery)
            ->whereNotNull('user_id')
            ->select('user_type', 'user_id', DB::raw('COUNT(*) as count'))
            ->groupBy('user_type', 'user_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['actor' => class_basename($r->user_type ?? '').' #'.$r->user_id, 'count' => $r->count])
            ->all();

        $stats = [
            'total' => $total,
            'by_event' => $byEvent,
            'top_models' => $topModels,
            'top_actors' => $topActors,
        ];

        if ($this->option('json')) {
            TerminalRenderer::json($stats);

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("  <info>Total audits:</info> {$total}".($since ? " (since {$since->toDateString()})" : ''));
        $this->newLine();
        $this->line('  <comment>By event:</comment>');
        $eventRows = array_map(fn ($e, $c) => ['event' => $e, 'count' => $c], array_keys($byEvent), array_values($byEvent));
        TerminalRenderer::table(['event', 'count'], $eventRows);

        $this->newLine();
        $this->line('  <comment>Top models:</comment>');
        TerminalRenderer::table(['model', 'count'], $topModels);

        $this->newLine();
        $this->line('  <comment>Top actors:</comment>');
        TerminalRenderer::table(['actor', 'count'], $topActors);

        $this->newLine();

        return self::SUCCESS;
    }
}
