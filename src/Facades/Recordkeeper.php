<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the Recordkeeper audit trail service.
 *
 * @method static mixed batch(string $id, \Closure $callback)
 * @method static ?string currentBatchId()
 * @method static array currentTags()
 * @method static static withTags(array $tags)
 * @method static \LaraArabDev\Recordkeeper\Models\Audit log(string $event, ?\Illuminate\Database\Eloquent\Model $subject = null, array $context = [])
 * @method static mixed rollback(int|string|\LaraArabDev\Recordkeeper\Models\Audit $auditOrId, bool $dryRun = false)
 * @method static void rollbackAsync(int|string|\LaraArabDev\Recordkeeper\Models\Audit $auditOrId)
 * @method static array rollbackBatch(string $id, bool $dryRun = false)
 * @method static void rollbackBatchAsync(string $id)
 * @method static static resolveActorUsing(\Closure $resolver)
 * @method static mixed resolveActor()
 * @method static bool isEnabled()
 * @method static void pushContext(array $context)
 * @method static void clearContext()
 * @method static array normalizeContext(mixed $context)
 *
 * @see \LaraArabDev\Recordkeeper\Recordkeeper
 */
class Recordkeeper extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \LaraArabDev\Recordkeeper\Recordkeeper::class;
    }
}
