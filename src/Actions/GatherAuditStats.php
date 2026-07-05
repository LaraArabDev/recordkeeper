<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Aggregate audit statistics by event, model, and actor.
 *
 * @return array{total: int, by_event: array, top_models: array, top_actors: array, since: ?Carbon}
 */
final class GatherAuditStats
{
    public function __invoke(?string $sinceRaw = null): array
    {
        $since = null;
        if ($sinceRaw !== null) {
            $ts = strtotime($sinceRaw);
            $since = $ts !== false ? Carbon::createFromTimestamp($ts) : null;
        }

        $base = Audit::query();
        if ($since) {
            $base->where('created_at', '>=', $since);
        }

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
}
