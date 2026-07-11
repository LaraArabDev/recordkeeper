<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Support;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LaraArabDev\Recordkeeper\Events\RollbackCompleted;
use LaraArabDev\Recordkeeper\Events\RollbackStarted;
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Jobs\ProcessRollback;
use LaraArabDev\Recordkeeper\Jobs\ProcessRollbackCollection;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Modifiers\EncryptAttribute;

/**
 * Handle audit rollback operations: undo creates, updates, and deletions.
 *
 * Automatically decrypts encrypted values before restoring and disables
 * auditing during rollback to prevent recursive audit entries.
 */
final class Rollback
{
    /**
     * Revert a single audit based on its event type.
     *
     * @throws \RuntimeException If rollback is disabled in config.
     */
    public function revert(Audit $audit, bool $dryRun = false, bool $recordTrail = true): mixed
    {
        if (! config('recordkeeper.rollback.enabled', true)) {
            throw new \RuntimeException('Rollback is disabled in recordkeeper config.');
        }

        RollbackStarted::dispatch($audit, $dryRun);

        $result = match (true) {
            $audit->event === 'created' => $this->undoCreate($audit, $dryRun),
            in_array($audit->event, ['deleted', 'forceDeleted'], true) => $this->restore($audit, $dryRun),
            default => $this->undoUpdate($audit, $dryRun),
        };

        if (! $dryRun && $recordTrail && config('recordkeeper.rollback.track', true)) {
            $this->recordTrail($audit);
        }

        RollbackCompleted::dispatch($audit, $result);

        return $result;
    }

    /**
     * Dispatch a single audit rollback to the queue.
     */
    public function revertAsync(Audit $audit, bool $recordTrail = true): void
    {
        $job = new ProcessRollback($audit->id, $recordTrail);

        $job->onConnection(config('recordkeeper.queue.connection'))
            ->onQueue(config('recordkeeper.queue.queue', 'audits'));

        dispatch($job);
    }

    /**
     * Dispatch a collection of audit rollbacks to the queue.
     *
     * @param  Collection<int, Audit>  $audits
     */
    public function revertCollectionAsync(Collection $audits, bool $recordTrail = true): void
    {
        $job = new ProcessRollbackCollection(
            $audits->pluck('id')->map(fn ($id) => (int) $id)->all(),
            $recordTrail,
        );

        $job->onConnection(config('recordkeeper.queue.connection'))
            ->onQueue(config('recordkeeper.queue.queue', 'audits'));

        dispatch($job);
    }

    /**
     * Revert all rollbackable audits in a batch, newest first, within a transaction.
     *
     * @return list<mixed>
     */
    public function revertBatch(string $batchId, bool $dryRun = false, bool $recordTrail = true): array
    {
        $audits = Audit::where('batch_id', $batchId)
            ->with('auditable')
            ->whereIn('event', Audit::ROLLBACKABLE_EVENTS)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        if ($dryRun) {
            return $audits->map(fn (Audit $a) => $this->revert($a, true, $recordTrail))->all();
        }

        return DB::transaction(function () use ($audits, $recordTrail): array {
            return $audits->map(fn (Audit $a) => $this->revert($a, false, $recordTrail))->all();
        });
    }

    /**
     * Revert an arbitrary collection of audits in reverse chronological order within a transaction.
     *
     * @param  Collection<int, Audit>  $audits
     * @return list<mixed>
     */
    public function revertCollection(Collection $audits, bool $dryRun = false, bool $recordTrail = true): array
    {
        $sorted = $audits->sortByDesc('created_at')->values();

        if ($dryRun) {
            return $sorted->map(fn (Audit $a) => $this->revert($a, true, $recordTrail))->all();
        }

        return DB::transaction(function () use ($sorted, $recordTrail): array {
            return $sorted->map(fn (Audit $a) => $this->revert($a, false, $recordTrail))->all();
        });
    }

    /**
     * Record a rollback audit trail entry.
     */
    private function recordTrail(Audit $audit): void
    {
        $eventName = match (true) {
            $audit->event === 'created' => 'rollback.undo_create',
            in_array($audit->event, ['deleted', 'forceDeleted'], true) => 'rollback.restore',
            default => 'rollback.undo_update',
        };

        $subject = $audit->auditable;

        Recordkeeper::log($eventName, $subject, [
            'original_audit_id' => $audit->id,
            'original_event' => $audit->event,
            'original_changes' => $audit->getModified(),
        ]);
    }

    private function undoCreate(Audit $audit, bool $dryRun): mixed
    {
        if ($dryRun) {
            return [
                'action' => 'delete',
                'auditable_type' => $audit->auditable_type,
                'auditable_id' => $audit->auditable_id,
            ];
        }

        $model = $audit->auditable;

        if ($model === null) {
            return null;
        }

        $model->disableAuditing();

        try {
            return $model->forceDelete();
        } finally {
            $model->enableAuditing();
        }
    }

    private function restore(Audit $audit, bool $dryRun): mixed
    {
        if (! config('recordkeeper.rollback.restore_deleted', true)) {
            throw new \RuntimeException('restore_deleted is disabled in recordkeeper config.');
        }

        $oldValues = $this->decryptValues((array) ($audit->old_values ?? []));

        if ($dryRun) {
            return ['action' => 'restore', 'attributes' => $oldValues];
        }

        $modelClass = $audit->auditable_type;

        $usesSoftDeletes = in_array(
            SoftDeletes::class,
            class_uses_recursive($modelClass),
        );

        if ($usesSoftDeletes) {
            $existing = $modelClass::withTrashed()->find($audit->auditable_id);
            if ($existing !== null) {
                $existing->disableAuditing();

                try {
                    $existing->restore();
                } finally {
                    $existing->enableAuditing();
                }

                return $existing;
            }
        }

        $instance = new $modelClass;
        $instance->disableAuditing();

        try {
            $instance->fill(array_merge($oldValues, [$instance->getKeyName() => $audit->auditable_id]));
            $instance->save();
        } finally {
            $instance->enableAuditing();
        }

        return $instance;
    }

    private function undoUpdate(Audit $audit, bool $dryRun): mixed
    {
        if ($dryRun) {
            return ['action' => 'update', 'attributes' => $audit->getModified()];
        }

        $model = $audit->auditable;

        if ($model === null) {
            return null;
        }

        $oldValues = $this->decryptValues((array) ($audit->old_values ?? []));

        $model->disableAuditing();

        try {
            $model->fill($oldValues)->save();
        } finally {
            $model->enableAuditing();
        }

        return $model;
    }

    /**
     * Decrypt any encrypted values in the array before restoring to the model.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function decryptValues(array $values): array
    {
        return array_map(function (mixed $value): mixed {
            if (is_string($value) && EncryptAttribute::isEncrypted($value)) {
                return EncryptAttribute::decrypt($value);
            }

            return $value;
        }, $values);
    }
}
