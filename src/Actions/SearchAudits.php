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
     * Execute the audit search with the given filters, limit, and offset.
     *
     * @param  array{model?: string, subject_id?: int|string, event?: string|list<string>, user?: int|string, user_type?: string, guard?: string, tag?: string, batch?: string, since?: string, until?: string, q?: string}  $filters  The search filters to apply.
     * @param  int  $limit  Maximum number of records to return.
     * @param  int  $offset  Number of records to skip before returning results.
     * @return Collection<int, Audit> The matching audit records.
     */
    public function __invoke(array $filters = [], int $limit = 25, int $offset = 0): Collection
    {
        $query = $this->applyFilters(new AuditQuery, $filters);

        return $query->latest()->limit($limit)->offset($offset)->builder()->get();
    }

    /**
     * Apply each non-empty filter to the query builder.
     *
     * @param  AuditQuery  $query  The query builder instance to apply filters to.
     * @param  array<string, mixed>  $filters  The filters to apply.
     * @return AuditQuery The query builder with all applicable filters applied.
     */
    private function applyFilters(AuditQuery $query, array $filters): AuditQuery
    {
        $simple = ['model', 'event', 'guard', 'tag', 'batch'];

        foreach ($simple as $key) {
            if (! empty($filters[$key])) {
                $query->{$key}($filters[$key]);
            }
        }

        if (! empty($filters['subject_id'])) {
            $query->subjectId($filters['subject_id']);
        }

        if (! empty($filters['user'])) {
            $query->actor($filters['user'], $filters['user_type'] ?? null);
        } elseif (! empty($filters['user_type'])) {
            $query->actorType($filters['user_type']);
        }

        if (! empty($filters['since'])) {
            $query->between($filters['since'], $filters['until'] ?? now());
        }

        if (! empty($filters['q'])) {
            $query->search($filters['q']);
        }

        return $query;
    }
}
