<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Aggregate audit statistics by event, model, and actor.
 */
final class GatherAuditStats
{
    /**
     * Gather aggregated audit statistics, optionally filtered by a date threshold.
     *
     * @param  string|null  $sinceRaw  A human-readable date string (e.g. "7 days ago") to filter from, or null for all records.
     * @return array{total: int, by_event: array, top_models: array, top_actors: array, since: ?Carbon}
     */
    public function __invoke(?string $sinceRaw = null): array
    {
        $since = $this->parseSince($sinceRaw);

        $base = Audit::query()->when($since, fn ($q) => $q->where('created_at', '>=', $since));

        return [
            'total' => (clone $base)->count(),
            'by_event' => (clone $base)
                ->select('event', DB::raw('COUNT(*) as count'))
                ->groupBy('event')
                ->orderByDesc('count')
                ->get()
                ->mapWithKeys(fn ($r) => [$r->event => $r->count])
                ->all(),
            'top_models' => (clone $base)
                ->select('auditable_type', DB::raw('COUNT(*) as count'))
                ->groupBy('auditable_type')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->map(fn ($r) => ['model' => class_basename($r->auditable_type), 'count' => $r->count])
                ->all(),
            'top_actors' => (clone $base)
                ->whereNotNull('user_id')
                ->select('user_type', 'user_id', DB::raw('COUNT(*) as count'))
                ->groupBy('user_type', 'user_id')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->map(fn ($r) => ['actor' => class_basename($r->user_type ?? '').' #'.$r->user_id, 'count' => $r->count])
                ->all(),
            'since' => $since,
        ];
    }

    /**
     * Parse a human-readable date string into a Carbon instance.
     *
     * @param  string|null  $sinceRaw  The raw date string to parse, or null.
     * @return Carbon|null The parsed Carbon instance, or null if input is null or unparseable.
     */
    private function parseSince(?string $sinceRaw): ?Carbon
    {
        if ($sinceRaw === null) {
            return null;
        }

        $ts = strtotime($sinceRaw);

        return $ts !== false ? Carbon::createFromTimestamp($ts) : null;
    }
}
