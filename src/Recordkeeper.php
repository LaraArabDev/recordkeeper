<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper;

use Closure;
use Illuminate\Database\Eloquent\Model;
use LaraArabDev\Recordkeeper\Actions\RecordAudit;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Events\ChangeRecorded;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\Rollback;

/**
 * Core service for audit trail management.
 *
 * Provide batch grouping, manual event logging, rollback orchestration,
 * and actor resolution for the entire Recordkeeper package.
 */
class Recordkeeper
{
    private ?string $currentBatchId = null;

    private array $currentTags = [];

    private array $context = [];

    private ?Closure $actorResolver = null;

    public function __construct(
        private readonly Rollback $rollback,
        private readonly RecordAudit $recordAudit,
    ) {}

    /**
     * Execute a callback within a named audit batch.
     *
     * All audits recorded inside the callback share the same batch ID,
     * enabling atomic rollback via {@see rollbackBatch()}.
     */
    public function batch(string $id, Closure $callback): mixed
    {
        $previous = $this->currentBatchId;
        $this->currentBatchId = $id;

        try {
            return $callback();
        } finally {
            $this->currentBatchId = $previous;
        }
    }

    public function currentBatchId(): ?string
    {
        return $this->currentBatchId;
    }

    public function currentTags(): array
    {
        return $this->currentTags;
    }

    public function withTags(array $tags): static
    {
        $this->currentTags = $tags;

        return $this;
    }

    /**
     * Enrich an audit row with the current batch ID and accumulated context.
     *
     * @param  array<string, mixed>  $auditRow
     * @return array<string, mixed>
     */
    public function decorate(array $auditRow): array
    {
        if ($this->currentBatchId !== null) {
            $auditRow['batch_id'] = $this->currentBatchId;
        }

        if (! empty($this->context)) {
            $existing = $auditRow['context'] ?? [];
            if (is_string($existing)) {
                $existing = json_decode($existing, true) ?? [];
            }
            $auditRow['context'] = array_merge((array) $existing, $this->context);
        }

        return $auditRow;
    }

    /**
     * Append key-value pairs to the ambient context applied to subsequent audits.
     *
     * @param  array<string, mixed>  $context
     */
    public function pushContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }

    public function clearContext(): void
    {
        $this->context = [];
    }

    /**
     * Record a manual audit event outside the Eloquent observer flow.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(string $event, ?Model $subject = null, array $context = []): Audit
    {
        $payload = new AuditPayload(
            event: $event,
            auditableType: $subject ? $subject::class : 'system',
            auditableId: $subject?->getKey(),
            oldValues: [],
            newValues: [],
            batchId: $this->currentBatchId,
            context: array_merge($this->context, $context),
            source: $subject ? $subject::class : null,
        );

        $audit = ($this->recordAudit)($payload);

        ChangeRecorded::dispatch($audit);

        return $audit;
    }

    /**
     * Revert a single audit by ID or instance.
     *
     * @throws \RuntimeException If rollback is disabled in config.
     */
    public function rollback(int|string|Audit $auditOrId, bool $dryRun = false): mixed
    {
        $audit = $auditOrId instanceof Audit ? $auditOrId : Audit::findOrFail($auditOrId);

        return $this->rollback->revert($audit, $dryRun);
    }

    /**
     * Dispatch a single audit rollback to the queue.
     */
    public function rollbackAsync(int|string|Audit $auditOrId): void
    {
        $audit = $auditOrId instanceof Audit ? $auditOrId : Audit::findOrFail($auditOrId);

        $this->rollback->revertAsync($audit);
    }

    /**
     * Revert all audits in a batch atomically within a DB transaction.
     *
     * @return list<mixed>
     *
     * @throws \RuntimeException If rollback is disabled in config.
     */
    public function rollbackBatch(string $id, bool $dryRun = false): array
    {
        return $this->rollback->revertBatch($id, $dryRun);
    }

    /**
     * Dispatch a batch rollback to the queue.
     */
    public function rollbackBatchAsync(string $id): void
    {
        $audits = Audit::where('batch_id', $id)
            ->with('auditable')
            ->whereIn('event', Audit::ROLLBACKABLE_EVENTS)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $this->rollback->revertCollectionAsync($audits);
    }

    public function resolveActorUsing(Closure $resolver): static
    {
        $this->actorResolver = $resolver;

        return $this;
    }

    public function resolveActor(): mixed
    {
        if ($this->actorResolver !== null) {
            return ($this->actorResolver)();
        }

        return auth()->user();
    }

    public function isEnabled(): bool
    {
        return (bool) config('recordkeeper.enabled', true);
    }
}
