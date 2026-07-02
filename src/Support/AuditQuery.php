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

    /** Filter by auditable model class (FQCN or short basename). */
    public function model(string $type): static
    {
        if (! str_contains($type, '\\')) {
            $this->query->where('auditable_type', 'like', '%\\'.$type);
        } else {
            $this->query->where('auditable_type', $type);
        }

        return $this;
    }

    public function subjectId(int|string $id): static
    {
        $this->query->where('auditable_id', $id);

        return $this;
    }

    public function event(string|array $event): static
    {
        $this->query->whereIn('event', (array) $event);

        return $this;
    }

    public function rollbackable(): static
    {
        $this->query->whereIn('event', ['created', 'updated', 'deleted', 'restored']);

        return $this;
    }

    public function actor(int|string $userId, ?string $userType = null): static
    {
        $this->query->where('user_id', $userId);

        if ($userType !== null) {
            if (! str_contains($userType, '\\')) {
                $this->query->where('user_type', 'like', '%\\'.$userType);
            } else {
                $this->query->where('user_type', $userType);
            }
        }

        return $this;
    }

    public function actorType(string $userType): static
    {
        if (! str_contains($userType, '\\')) {
            $this->query->where('user_type', 'like', '%\\'.$userType);
        } else {
            $this->query->where('user_type', $userType);
        }

        return $this;
    }

    public function onlyAuthenticated(): static
    {
        $this->query->whereNotNull('user_id');

        return $this;
    }

    public function guard(string $guard): static
    {
        $this->query->where('guard', $guard);

        return $this;
    }

    public function tag(string|array $tags): static
    {
        foreach ((array) $tags as $tag) {
            $this->query->where('tags', 'like', '%'.$tag.'%');
        }

        return $this;
    }

    public function batch(string $batchId): static
    {
        $this->query->where('batch_id', $batchId);

        return $this;
    }

    public function between(\DateTimeInterface|string $from, \DateTimeInterface|string $until): static
    {
        $this->query->whereBetween('created_at', [$from, $until]);

        return $this;
    }

    public function since(\DateTimeInterface|string $from): static
    {
        $this->query->where('created_at', '>=', $from);

        return $this;
    }

    /** Free-text search across event, auditable_type, batch_id, and user_id. */
    public function search(string $term): static
    {
        $this->query->where(function (Builder $q) use ($term): void {
            $q->where('event', 'like', '%'.$term.'%')
                ->orWhere('auditable_type', 'like', '%'.$term.'%')
                ->orWhere('batch_id', 'like', '%'.$term.'%')
                ->orWhere('user_id', 'like', '%'.$term.'%');
        });

        return $this;
    }

    public function latest(): static
    {
        $this->query->latest('created_at');

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->query->limit($limit);

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->query->offset($offset);

        return $this;
    }

    public function jobs(): static
    {
        $this->query->where('event', 'like', 'job.%');

        return $this;
    }

    public function commands(): static
    {
        $this->query->where('event', 'like', 'command.%');

        return $this;
    }

    public function events(): static
    {
        $this->query->where('event', 'like', 'event.%');

        return $this;
    }

    /** @return Builder<Audit> */
    public function builder(): Builder
    {
        return $this->query;
    }
}
