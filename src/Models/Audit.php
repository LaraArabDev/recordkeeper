<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LaraArabDev\Recordkeeper\Support\Rollback;
use OwenIt\Auditing\Models\Audit as BaseAudit;

/**
 * Extended audit model with scopes, pruning, rollback, and HTTP request tracking.
 *
 * @property int $id
 * @property string $event
 * @property string $auditable_type
 * @property int|string|null $auditable_id
 * @property array<string, mixed> $old_values
 * @property array<string, mixed> $new_values
 * @property array<string, mixed>|null $context
 * @property string|null $user_type
 * @property int|string|null $user_id
 * @property string|null $url
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $batch_id
 * @property string|null $tags
 * @property string|null $source
 * @property string|null $guard
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> forGuard(string $guard)
 * @method static Builder<static> forModel(string $model)
 * @method static Builder<static> forSubject(Model $subject)
 * @method static Builder<static> forActorType(string $userType)
 * @method static Builder<static> forActor(Model|int|string $actor, ?string $userType = null)
 * @method static Builder<static> forBatch(string $batchId)
 * @method static Builder<static> rollbackable()
 * @method static Builder<static> routeHits()
 * @method static Builder<static> jobAudits()
 * @method static Builder<static> commandAudits()
 * @method static Builder<static> eventAudits()
 * @method static Builder<static> forTag(string $tag)
 * @method static Builder<static> forSource(string $source)
 */
class Audit extends BaseAudit
{
    use MassPrunable, SoftDeletes;

    /** @var list<string> */
    public const ROLLBACKABLE_EVENTS = ['created', 'updated', 'deleted', 'restored'];

    /** @var list<string> Pseudo-types that are not real Eloquent models. */
    public const PSEUDO_TYPES = ['route', 'system', 'job', 'command', 'event'];

    /**
     * Get the auditable model that this audit belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Sync tags from the comma-separated `tags` column into the pivot table on creation.
     */
    protected static function booted(): void
    {
        static::created(function (Audit $audit): void {
            if (! empty($audit->tags)) {
                $tags = array_filter(explode(',', $audit->tags));
                $audit->auditTags()->createMany(
                    array_map(fn ($t) => ['tag' => trim($t)], $tags)
                );
            }
        });
    }

    /** @var array<string, string> */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'context' => 'array',
    ];

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * Scope audits to a specific authentication guard.
     *
     * @param  Builder<static>  $query
     * @param  string  $guard  The authentication guard name.
     * @return Builder<static>
     */
    public function scopeForGuard(Builder $query, string $guard): Builder
    {
        return $query->where('guard', $guard);
    }

    /**
     * Scope audits by model class name (FQCN or short basename).
     *
     * @param  Builder<static>  $query
     * @param  string  $model  Fully qualified class name or short basename (e.g. "Order").
     * @return Builder<static>
     */
    public function scopeForModel(Builder $query, string $model): Builder
    {
        if (! str_contains($model, '\\')) {
            return $query->where('auditable_type', 'like', '%\\'.$model);
        }

        return $query->where('auditable_type', $model);
    }

    /**
     * Scope audits to a specific auditable subject (model instance).
     *
     * @param  Builder<static>  $query
     * @param  Model  $subject  The auditable model instance to filter by.
     * @return Builder<static>
     */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('auditable_type', $subject::class)
            ->where('auditable_id', $subject->getKey());
    }

    /**
     * Scope audits by actor type (FQCN or short basename).
     *
     * @param  Builder<static>  $query
     * @param  string  $userType  Fully qualified class name or short basename (e.g. "User").
     * @return Builder<static>
     */
    public function scopeForActorType(Builder $query, string $userType): Builder
    {
        if (! str_contains($userType, '\\')) {
            return $query->where('user_type', 'like', '%\\'.$userType);
        }

        return $query->where('user_type', $userType);
    }

    /**
     * Scope audits by actor (model instance or user ID with optional type).
     *
     * When a Model is passed, both user_type and user_id are matched.
     * When an ID is passed with a short $userType, a LIKE query is used.
     *
     * @param  Builder<static>  $query
     * @param  Model|int|string  $actor  Model instance or user ID.
     * @param  string|null  $userType  FQCN or short basename to filter actor type.
     * @return Builder<static>
     */
    public function scopeForActor(Builder $query, Model|int|string $actor, ?string $userType = null): Builder
    {
        if ($actor instanceof Model) {
            return $query
                ->where('user_type', $actor::class)
                ->where('user_id', $actor->getKey());
        }

        $query->where('user_id', $actor);

        if ($userType !== null) {
            $query->forActorType($userType);
        }

        return $query;
    }

    /**
     * Scope audits to a specific batch ID.
     *
     * @param  Builder<static>  $query
     * @param  string  $batchId  The batch identifier to filter by.
     * @return Builder<static>
     */
    public function scopeForBatch(Builder $query, string $batchId): Builder
    {
        return $query->where('batch_id', $batchId);
    }

    /**
     * Scope audits to only rollbackable events (created, updated, deleted, restored).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRollbackable(Builder $query): Builder
    {
        return $query->whereIn('event', self::ROLLBACKABLE_EVENTS);
    }

    /**
     * Scope audits to HTTP route hits (event starts with "route.").
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRouteHits(Builder $query): Builder
    {
        return $query->where('event', 'like', 'route.%');
    }

    /**
     * Scope audits to queued job events (event starts with "job.").
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeJobAudits(Builder $query): Builder
    {
        return $query->where('event', 'like', 'job.%');
    }

    /**
     * Scope audits to Artisan command events (event starts with "command.").
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCommandAudits(Builder $query): Builder
    {
        return $query->where('event', 'like', 'command.%');
    }

    /**
     * Scope audits to custom application events (event starts with "event.").
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEventAudits(Builder $query): Builder
    {
        return $query->where('event', 'like', 'event.%');
    }

    /**
     * Get the tags associated with this audit via the pivot table.
     *
     * @return HasMany<AuditTag, $this>
     */
    public function auditTags(): HasMany
    {
        return $this->hasMany(AuditTag::class);
    }

    /**
     * Scope audits to those having a specific tag via the pivot table.
     *
     * @param  Builder<static>  $query
     * @param  string  $tag  The tag value to filter by.
     * @return Builder<static>
     */
    public function scopeForTag(Builder $query, string $tag): Builder
    {
        return $query->whereHas('auditTags', fn (Builder $q) => $q->where('tag', $tag));
    }

    /**
     * Scope audits by source identifier (FQCN, route, or command name).
     *
     * @param  Builder<static>  $query
     * @param  string  $source  The source identifier to filter by.
     * @return Builder<static>
     */
    public function scopeForSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    /**
     * Define the prunable query based on configured retention days.
     *
     * Returns a query matching no rows when retention is disabled (days <= 0).
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = (int) config('recordkeeper.retention.default_days', 0);

        if ($days <= 0) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where('created_at', '<', Carbon::now()->subDays($days));
    }

    /**
     * Override to force-delete pruned audits instead of soft-deleting.
     *
     * @param  int  $chunkSize  The number of records to delete per iteration.
     * @return int The total number of pruned records.
     */
    public function pruneAll(int $chunkSize = 1000): int
    {
        $size = (int) config('recordkeeper.drivers.database.chunk_size', $chunkSize);
        $total = 0;

        do {
            $count = $this->prunable()->limit($size)->forceDelete();
            $total += $count;
        } while ($count > 0);

        return $total;
    }

    /**
     * Revert this audit's changes, restoring the model to its previous state.
     *
     * @param  bool  $dryRun  When true, return a preview without applying changes.
     * @return Model|array<string, mixed>|bool|null The restored model, a dry-run preview array, or null if the subject no longer exists.
     *
     * @throws \RuntimeException If rollback is disabled in config.
     */
    public function rollback(bool $dryRun = false): mixed
    {
        return app(Rollback::class)->revert($this, $dryRun);
    }

    /**
     * Get the outbound HTTP requests recorded during this audit's job execution.
     *
     * @return HasMany<AuditHttpRequest, $this>
     */
    public function httpRequests(): HasMany
    {
        return $this->hasMany(AuditHttpRequest::class);
    }

    /**
     * Determine whether this audit's event type supports rollback.
     */
    public function isRollbackable(): bool
    {
        return in_array($this->event, self::ROLLBACKABLE_EVENTS, true);
    }
}
