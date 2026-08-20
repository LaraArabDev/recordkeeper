<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Support;

use Illuminate\Database\Eloquent\Builder;
use LaraArabDev\Recordkeeper\Models\Audit;

/**
 * Fluent query builder for searching and filtering audit records.
 *
 * Wraps an Eloquent builder with a chainable, domain-specific API.
 */
final class AuditQuery
{
    private Builder $query;

    public function __construct()
    {
        $this->query = Audit::query()->with(['auditable']);
    }

    /**
     * Filter by auditable model class (FQCN or short basename).
     *
     * @param  string  $type  The fully-qualified class name or short basename to filter by.
     */
    public function model(string $type): static
    {
        $this->whereClassOrBasename('auditable_type', $type);

        return $this;
    }

    /**
     * Filter by auditable subject ID.
     *
     * @param  int|string  $id  The auditable model's primary key value.
     */
    public function subjectId(int|string $id): static
    {
        $this->query->where('auditable_id', $id);

        return $this;
    }

    /**
     * Filter by one or more event names.
     *
     * @param  string|array<string>  $event  A single event name or array of event names.
     */
    public function event(string|array $event): static
    {
        $this->query->whereIn('event', (array) $event);

        return $this;
    }

    /**
     * Filter to only rollbackable audit events.
     */
    public function rollbackable(): static
    {
        $this->query->whereIn('event', Audit::ROLLBACKABLE_EVENTS);

        return $this;
    }

    /**
     * Filter by actor (user) ID and optional type.
     *
     * @param  int|string  $userId  The user ID to filter by.
     * @param  string|null  $userType  Optional FQCN or short basename of the user model type.
     */
    public function actor(int|string $userId, ?string $userType = null): static
    {
        $this->query->where('user_id', $userId);

        if ($userType !== null) {
            $this->actorType($userType);
        }

        return $this;
    }

    /**
     * Filter by actor type (FQCN or short basename).
     *
     * @param  string  $userType  The fully-qualified class name or short basename of the user type.
     */
    public function actorType(string $userType): static
    {
        $this->whereClassOrBasename('user_type', $userType);

        return $this;
    }

    /**
     * Filter to only audits performed by authenticated users.
     */
    public function onlyAuthenticated(): static
    {
        $this->query->whereNotNull('user_id');

        return $this;
    }

    /**
     * Filter by authentication guard name.
     *
     * @param  string  $guard  The guard name to filter by.
     */
    public function guard(string $guard): static
    {
        $this->query->where('guard', $guard);

        return $this;
    }

    /**
     * Filter by one or more tags (exact match via pivot table).
     *
     * @param  string|array<string>  $tags  A single tag or array of tags to filter by.
     */
    public function tag(string|array $tags): static
    {
        foreach ((array) $tags as $tag) {
            $this->query->whereHas('auditTags', fn (Builder $q) => $q->where('tag', $tag));
        }

        return $this;
    }

    /**
     * Filter by source (FQCN, command name, route name, or path).
     *
     * @param  string  $source  The source identifier to filter by.
     */
    public function source(string $source): static
    {
        $this->query->where('source', $source);

        return $this;
    }

    /**
     * Filter by batch ID.
     *
     * @param  string  $batchId  The batch identifier to filter by.
     */
    public function batch(string $batchId): static
    {
        $this->query->where('batch_id', $batchId);

        return $this;
    }

    /**
     * Filter audits created between two dates.
     *
     * @param  \DateTimeInterface|string  $from  The start date (inclusive).
     * @param  \DateTimeInterface|string  $until  The end date (inclusive).
     */
    public function between(\DateTimeInterface|string $from, \DateTimeInterface|string $until): static
    {
        $this->query->whereBetween('created_at', [$from, $until]);

        return $this;
    }

    /**
     * Filter audits created since a given date.
     *
     * @param  \DateTimeInterface|string  $from  The start date (inclusive).
     */
    public function since(\DateTimeInterface|string $from): static
    {
        $this->query->where('created_at', '>=', $from);

        return $this;
    }

    /**
     * Filter audits created before a given date.
     *
     * @param  \DateTimeInterface|string  $until  The end date (inclusive).
     */
    public function until(\DateTimeInterface|string $until): static
    {
        $this->query->where('created_at', '<=', $until);

        return $this;
    }

    /**
     * Free-text search across event, auditable_type, and batch_id.
     *
     * @param  string  $term  The search term to match against multiple columns.
     */
    public function search(string $term): static
    {
        $this->query->where(function (Builder $q) use ($term): void {
            $q->where('event', 'like', '%'.$term.'%')
                ->orWhere('auditable_type', 'like', '%'.$term.'%')
                ->orWhere('batch_id', 'like', '%'.$term.'%');
        });

        return $this;
    }

    /**
     * Order results by most recent first.
     */
    public function latest(): static
    {
        $this->query->latest('created_at')->latest('id');

        return $this;
    }

    /**
     * Limit the number of results returned.
     *
     * @param  int  $limit  Maximum number of results to return.
     */
    public function limit(int $limit): static
    {
        $this->query->limit($limit);

        return $this;
    }

    /**
     * Skip a number of results before returning.
     *
     * @param  int  $offset  Number of results to skip.
     */
    public function offset(int $offset): static
    {
        $this->query->offset($offset);

        return $this;
    }

    /**
     * Paginate results by limit and page number.
     *
     * @param  int  $limit  Number of results per page.
     * @param  int  $page  The page number (1-based).
     */
    public function paginate(int $limit, int $page): static
    {
        return $this->limit($limit)->offset(($page - 1) * $limit);
    }

    /**
     * Filter to only job audit events.
     */
    public function jobs(): static
    {
        $this->query->where('event', 'like', 'job.%');

        return $this;
    }

    /**
     * Filter to only command audit events.
     */
    public function commands(): static
    {
        $this->query->where('event', 'like', 'command.%');

        return $this;
    }

    /**
     * Filter to only application event audit events.
     */
    public function events(): static
    {
        $this->query->where('event', 'like', 'event.%');

        return $this;
    }

    /**
     * Get the underlying Eloquent query builder.
     *
     * @return Builder<Audit>
     */
    public function builder(): Builder
    {
        return $this->query;
    }

    /**
     * Apply a where clause matching FQCN exactly or basename via LIKE.
     *
     * @param  string  $column  The database column to filter on.
     * @param  string  $value  The FQCN (exact match) or short basename (LIKE suffix match).
     */
    private function whereClassOrBasename(string $column, string $value): void
    {
        str_contains($value, '\\')
            ? $this->query->where($column, $value)
            : $this->query->where($column, 'like', '%\\'.$value);
    }
}
