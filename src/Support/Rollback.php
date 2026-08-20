<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LaraArabDev\Recordkeeper\Actions\RecordAudit;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Events\RollbackCompleted;
use LaraArabDev\Recordkeeper\Events\RollbackStarted;
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Jobs\ProcessRollback;
use LaraArabDev\Recordkeeper\Jobs\ProcessRollbackCollection;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Modifiers\EncryptAttribute;

/**
 * Revert audit records: undo creates, updates, and deletions.
 *
 * Decrypts encrypted values before restoring and disables
 * auditing during rollback to prevent recursive entries.
 */
final class Rollback
{
    /**
     * Revert a single audit.
     *
     * @param  Audit  $audit  The audit record to revert.
     * @param  bool  $dryRun  If true, return what would happen without persisting changes.
     * @param  bool  $recordTrail  If true, log a rollback trail audit entry.
     * @return mixed The reverted model, a dry-run summary array, or null if the auditable no longer exists.
     *
     * @throws \RuntimeException If rollback is disabled.
     */
    public function revert(Audit $audit, bool $dryRun = false, bool $recordTrail = true): mixed
    {
        $this->ensureEnabled();

        RollbackStarted::dispatch($audit, $dryRun);

        $result = match ($audit->event) {
            'created' => $this->undoCreate($audit, $dryRun),
            'deleted', 'forceDeleted' => $this->restore($audit, $dryRun),
            default => $this->undoUpdate($audit, $dryRun),
        };

        if (! $dryRun && $recordTrail && config('recordkeeper.rollback.track', true)) {
            $this->recordTrail($audit);
        }

        RollbackCompleted::dispatch($audit, $result);

        return $result;
    }

    /**
     * Revert a batch of audits, newest first, within a transaction.
     *
     * @param  string  $batchId  The batch ID whose audits should be reverted.
     * @param  bool  $dryRun  If true, return what would happen without persisting changes.
     * @param  bool  $recordTrail  If true, log a rollback trail audit entry for each revert.
     * @return list<mixed> Results of each individual revert operation.
     */
    public function revertBatch(string $batchId, bool $dryRun = false, bool $recordTrail = true): array
    {
        $audits = Audit::where('batch_id', $batchId)
            ->with('auditable')
            ->whereIn('event', Audit::ROLLBACKABLE_EVENTS)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $this->revertMany($audits, $dryRun, $recordTrail);
    }

    /**
     * Revert a collection of audits in reverse chronological order.
     *
     * @param  Collection<int, Audit>  $audits  The audits to revert.
     * @param  bool  $dryRun  If true, return what would happen without persisting changes.
     * @param  bool  $recordTrail  If true, log a rollback trail audit entry for each revert.
     * @return list<mixed> Results of each individual revert operation.
     */
    public function revertCollection(Collection $audits, bool $dryRun = false, bool $recordTrail = true): array
    {
        return $this->revertMany($audits->sortByDesc('created_at')->values(), $dryRun, $recordTrail);
    }

    /**
     * Dispatch a single rollback to the queue.
     *
     * @param  Audit  $audit  The audit record to revert asynchronously.
     * @param  bool  $recordTrail  If true, log a rollback trail audit entry when processed.
     */
    public function revertAsync(Audit $audit, bool $recordTrail = true): void
    {
        $this->dispatchToQueue(new ProcessRollback($audit->id, $recordTrail));
    }

    /**
     * Dispatch a collection rollback to the queue.
     *
     * @param  Collection<int, Audit>  $audits  The audits to revert asynchronously.
     * @param  bool  $recordTrail  If true, log a rollback trail audit entry when processed.
     */
    public function revertCollectionAsync(Collection $audits, bool $recordTrail = true): void
    {
        $ids = $audits->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->dispatchToQueue(new ProcessRollbackCollection($ids, $recordTrail));
    }

    /**
     * Revert multiple audits, wrapping in a transaction unless dry-running.
     *
     * @param  Collection<int, Audit>  $audits  The audits to revert.
     * @param  bool  $dryRun  If true, skip the database transaction and just compute results.
     * @param  bool  $recordTrail  If true, log a rollback trail audit entry for each revert.
     * @return list<mixed> Results of each individual revert operation.
     */
    private function revertMany(Collection $audits, bool $dryRun, bool $recordTrail): array
    {
        $revert = fn (Audit $a) => $this->revert($a, $dryRun, $recordTrail);

        if ($dryRun) {
            return $audits->map($revert)->all();
        }

        return DB::transaction(fn () => $audits->map($revert)->all());
    }

    /**
     * Undo a "created" event by force-deleting the model.
     *
     * @param  Audit  $audit  The audit record for the create event.
     * @param  bool  $dryRun  If true, return a summary array instead of deleting.
     * @return mixed The deletion result, a dry-run summary array, or null if the model no longer exists.
     */
    private function undoCreate(Audit $audit, bool $dryRun): mixed
    {
        if ($dryRun) {
            return ['action' => 'delete', 'auditable_type' => $audit->auditable_type, 'auditable_id' => $audit->auditable_id];
        }

        $model = $audit->auditable;

        if ($model === null) {
            return null;
        }

        return $this->withoutAuditing($model, fn () => $model->forceDelete());
    }

    /**
     * Undo an "updated" event by restoring old attribute values.
     *
     * @param  Audit  $audit  The audit record for the update event.
     * @param  bool  $dryRun  If true, return a summary array instead of updating.
     * @return mixed The restored model, a dry-run summary array, or null if the model no longer exists.
     */
    private function undoUpdate(Audit $audit, bool $dryRun): mixed
    {
        $oldValues = $this->decryptValues($audit->old_values ?? []);

        if ($dryRun) {
            return ['action' => 'update', 'attributes' => $oldValues];
        }

        $model = $audit->auditable;

        if ($model === null) {
            return null;
        }

        return $this->withoutAuditing($model, function () use ($model, $oldValues) {
            $model->fill($oldValues)->save();

            return $model;
        });
    }

    /**
     * Restore a deleted or force-deleted model from its audit snapshot.
     *
     * @param  Audit  $audit  The audit record for the delete or forceDelete event.
     * @param  bool  $dryRun  If true, return a summary array instead of restoring.
     * @return mixed The restored model or a dry-run summary array.
     *
     * @throws \RuntimeException If restore_deleted is disabled or force-deleted record has no old_values.
     */
    private function restore(Audit $audit, bool $dryRun): mixed
    {
        if (! config('recordkeeper.rollback.restore_deleted', true)) {
            throw new \RuntimeException('restore_deleted is disabled in recordkeeper config.');
        }

        $oldValues = $this->decryptValues($audit->old_values ?? []);

        if ($audit->event === 'forceDeleted' && empty($oldValues)) {
            throw new \RuntimeException(
                'Cannot rollback force-deleted record: no data in old_values.'
            );
        }

        if ($dryRun) {
            return ['action' => 'restore', 'attributes' => $oldValues];
        }

        $modelClass = Relation::getMorphedModel($audit->auditable_type) ?? $audit->auditable_type;

        // Soft-deleted records can be restored directly
        if ($this->usesSoftDeletes($modelClass)) {
            $existing = $modelClass::withTrashed()->find($audit->auditable_id);

            if ($existing !== null) {
                return $this->withoutAuditing($existing, function () use ($existing) {
                    $existing->restore();

                    return $existing;
                });
            }
        }

        // Re-create from old_values snapshot
        $instance = new $modelClass;

        return $this->withoutAuditing($instance, function () use ($instance, $oldValues, $audit) {
            $instance->forceFill([...$oldValues, $instance->getKeyName() => $audit->auditable_id]);
            $instance->save();

            return $instance;
        });
    }

    /**
     * Record a rollback trail audit entry for the reverted audit.
     *
     * @param  Audit  $audit  The original audit that was reverted.
     */
    private function recordTrail(Audit $audit): void
    {
        $eventName = match ($audit->event) {
            'created' => 'rollback.undo_create',
            'deleted', 'forceDeleted' => 'rollback.restore',
            default => 'rollback.undo_update',
        };

        app(RecordAudit::class)(new AuditPayload(
            event: $eventName,
            auditableType: $audit->auditable_type,
            auditableId: $audit->auditable_id,
            oldValues: [],
            newValues: [],
            batchId: Recordkeeper::currentBatchId(),
            context: [
                'original_audit_id' => $audit->id,
                'original_event' => $audit->event,
                'original_changes' => $audit->getModified(),
            ],
            source: $audit->auditable_type,
        ));
    }

    /**
     * Run a callback with auditing temporarily disabled on the model.
     *
     * @param  Model  $model  The model on which to disable auditing.
     * @param  callable  $callback  The callback to execute while auditing is disabled.
     * @return mixed The return value of the callback.
     */
    private function withoutAuditing(Model $model, callable $callback): mixed
    {
        $model->disableAuditing();

        try {
            return $callback();
        } finally {
            $model->enableAuditing();
        }
    }

    /**
     * Decrypt any encrypted attribute values in the given array.
     *
     * @param  array<string, mixed>  $values  The attribute values to process.
     * @return array<string, mixed> The values with encrypted entries decrypted.
     */
    private function decryptValues(array $values): array
    {
        return array_map(
            fn (mixed $v) => is_string($v) && EncryptAttribute::isEncrypted($v) ? EncryptAttribute::decrypt($v) : $v,
            $values,
        );
    }

    /**
     * Determine whether the given model class uses the SoftDeletes trait.
     *
     * @param  string  $class  The fully-qualified model class name.
     * @return bool True if the class uses SoftDeletes.
     */
    private function usesSoftDeletes(string $class): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($class), true);
    }

    /**
     * Ensure that rollback functionality is enabled in configuration.
     *
     * @throws \RuntimeException If rollback is disabled in the recordkeeper config.
     */
    private function ensureEnabled(): void
    {
        if (! config('recordkeeper.rollback.enabled', true)) {
            throw new \RuntimeException('Rollback is disabled in recordkeeper config.');
        }
    }

    /**
     * Dispatch a job to the configured audit queue.
     *
     * @param  object  $job  The job instance to dispatch.
     */
    private function dispatchToQueue(object $job): void
    {
        $job->onConnection(config('recordkeeper.queue.connection'))
            ->onQueue(config('recordkeeper.queue.queue', 'audits'));

        dispatch($job);
    }
}
