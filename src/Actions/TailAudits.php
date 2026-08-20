<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Actions;

use Illuminate\Database\Eloquent\Collection;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Poll for new audit records since a given ID, with optional filters.
 */
final class TailAudits
{
    /**
     * Fetch audits newer than the given ID, applying optional filters.
     *
     * @param  int  $lastId  The last seen audit ID; only records with a higher ID are returned.
     * @param  string|null  $model  Optional auditable model type to filter by (matched as suffix with LIKE).
     * @param  string|null  $event  Optional event name to filter by (exact match).
     * @param  string|null  $guard  Optional guard name to filter by (exact match).
     * @return Collection<int, Audit> The audit records newer than the given ID, ordered by ID ascending.
     */
    public function poll(int $lastId, ?string $model = null, ?string $event = null, ?string $guard = null): Collection
    {
        $query = Audit::where('id', '>', $lastId)->orderBy('id');

        if ($model) {
            $query->where('auditable_type', 'like', '%'.$model);
        }

        if ($event) {
            $query->where('event', $event);
        }

        if ($guard) {
            $query->where('guard', $guard);
        }

        return $query->get();
    }

    /**
     * Get the current maximum audit ID.
     *
     * @return int The highest audit ID, or 0 if no audits exist.
     */
    public function latestId(): int
    {
        return (int) (Audit::max('id') ?? 0);
    }
}
