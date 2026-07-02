<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Actions;

use Illuminate\Database\Eloquent\Collection;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AuditQuery;

/**
 * Search audit records using a filters array, with pagination support.
 */
final class SearchAudits
{
    /**
     * @param  array{model?: string, subject_id?: int|string, event?: string|list<string>, user?: int|string, user_type?: string, guard?: string, tag?: string, batch?: string, since?: string, until?: string, q?: string}  $filters
     * @return Collection<int, Audit>
     */
    public function __invoke(array $filters = [], int $limit = 25, int $offset = 0): Collection
    {
        $query = new AuditQuery;

        if (! empty($filters['model'])) {
            $query->model($filters['model']);
        }
        if (! empty($filters['subject_id'])) {
            $query->subjectId($filters['subject_id']);
        }
        if (! empty($filters['event'])) {
            $query->event($filters['event']);
        }
        if (! empty($filters['user'])) {
            $query->actor($filters['user'], $filters['user_type'] ?? null);
        } elseif (! empty($filters['user_type'])) {
            $query->actorType($filters['user_type']);
        }
        if (! empty($filters['guard'])) {
            $query->guard($filters['guard']);
        }
        if (! empty($filters['tag'])) {
            $query->tag($filters['tag']);
        }
        if (! empty($filters['batch'])) {
            $query->batch($filters['batch']);
        }
        if (! empty($filters['since'])) {
            $query->between($filters['since'], $filters['until'] ?? now());
        }
        if (! empty($filters['q'])) {
            $query->search($filters['q']);
        }

        return $query->latest()->limit($limit)->offset($offset)->builder()->get();
    }
}
