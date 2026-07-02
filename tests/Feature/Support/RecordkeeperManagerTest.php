<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Recordkeeper as RecordkeeperManager;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for the Recordkeeper core service (batching, logging, context, rollback, actor resolution).
 */
#[Group('support')]
#[CoversClass(RecordkeeperManager::class)]
class RecordkeeperManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
        app(RecordkeeperManager::class)->clearContext();
    }

    // ------------------------------------------------------------------
    // batch()
    // ------------------------------------------------------------------

    #[Test]
    public function batch_assigns_batch_id_to_all_writes_inside(): void
    {
        Recordkeeper::batch('my-batch', function (): void {
            Order::create(['status' => 'a']);
            Order::create(['status' => 'b']);
        });

        $this->assertSame(2, Audit::where('batch_id', 'my-batch')->count());
    }

    #[Test]
    public function batch_resets_batch_id_after_callback(): void
    {
        Recordkeeper::batch('temp', fn () => null);

        $this->assertNull(app(RecordkeeperManager::class)->currentBatchId());
    }

    #[Test]
    public function batch_resets_even_on_exception(): void
    {
        try {
            Recordkeeper::batch('failing', function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }

        $this->assertNull(app(RecordkeeperManager::class)->currentBatchId());
    }

    #[Test]
    public function nested_batch_restores_outer_batch(): void
    {
        $manager = app(RecordkeeperManager::class);

        Recordkeeper::batch('outer', function () use ($manager): void {
            $this->assertSame('outer', $manager->currentBatchId());

            Recordkeeper::batch('inner', function () use ($manager): void {
                $this->assertSame('inner', $manager->currentBatchId());
            });

            $this->assertSame('outer', $manager->currentBatchId());
        });

        $this->assertNull($manager->currentBatchId());
    }

    // ------------------------------------------------------------------
    // log()
    // ------------------------------------------------------------------

    #[Test]
    public function log_writes_arbitrary_event(): void
    {
        $audit = Recordkeeper::log('system.backup', null, ['size' => '2GB']);

        $this->assertDatabaseHas('audits', ['event' => 'system.backup']);
        $this->assertSame('2GB', $audit->context['size']);
    }

    #[Test]
    public function log_with_subject_model(): void
    {
        $order = Order::create(['status' => 'pending']);
        Audit::query()->delete(); // clear the created audit

        $audit = Recordkeeper::log('order.flagged', $order, ['reason' => 'suspicious']);

        $this->assertSame(Order::class, $audit->auditable_type);
        $this->assertSame((string) $order->id, (string) $audit->auditable_id);
    }

    #[Test]
    public function log_inherits_current_batch_id(): void
    {
        Recordkeeper::batch('log-batch', function (): void {
            Recordkeeper::log('custom.event');
        });

        $audit = Audit::where('event', 'custom.event')->first();
        $this->assertSame('log-batch', $audit->batch_id);
    }

    // ------------------------------------------------------------------
    // pushContext / decorate / clearContext
    // ------------------------------------------------------------------

    #[Test]
    public function push_context_is_merged_into_audit_row(): void
    {
        $manager = app(RecordkeeperManager::class);
        $manager->pushContext(['ticket' => 'JIRA-42', 'reason' => 'bulk-import']);

        Order::create(['status' => 'pending']);

        $audit = Audit::where('event', 'created')->first();
        $this->assertSame('JIRA-42', $audit->context['ticket'] ?? null);
        $this->assertSame('bulk-import', $audit->context['reason'] ?? null);

        $manager->clearContext();
    }

    #[Test]
    public function clear_context_removes_stashed_context(): void
    {
        $manager = app(RecordkeeperManager::class);
        $manager->pushContext(['foo' => 'bar']);
        $manager->clearContext();

        Order::create(['status' => 'ok']);

        $audit = Audit::first();
        $this->assertEmpty(array_filter((array) ($audit->context ?? [])));
    }

    #[Test]
    public function audit_context_helper_on_model(): void
    {
        $order = Order::create(['status' => 'pending']);
        Audit::query()->delete();

        $order->auditContext(['reason' => 'admin-edit'])->update(['status' => 'active']);

        $audit = Audit::where('event', 'updated')->first();
        $this->assertSame('admin-edit', $audit->context['reason'] ?? null);

        app(RecordkeeperManager::class)->clearContext();
    }

    // ------------------------------------------------------------------
    // withTags
    // ------------------------------------------------------------------

    #[Test]
    public function with_tags_injects_tags_into_audits(): void
    {
        $manager = app(RecordkeeperManager::class);
        $manager->withTags(['finance', 'q4']);

        Order::create(['status' => 'paid']);

        $audit = Audit::first();
        $this->assertStringContainsString('finance', (string) $audit->tags);
        $this->assertStringContainsString('q4', (string) $audit->tags);

        $manager->withTags([]);
    }

    // ------------------------------------------------------------------
    // isEnabled
    // ------------------------------------------------------------------

    #[Test]
    public function is_enabled_reflects_config(): void
    {
        config(['recordkeeper.enabled' => true]);
        $this->assertTrue(Recordkeeper::isEnabled());

        config(['recordkeeper.enabled' => false]);
        $this->assertFalse(Recordkeeper::isEnabled());

        config(['recordkeeper.enabled' => true]);
    }

    // ------------------------------------------------------------------
    // rollback via facade
    // ------------------------------------------------------------------

    #[Test]
    public function facade_rollback_reverts_update(): void
    {
        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $audit = Audit::where('event', 'updated')->first();
        Recordkeeper::rollback($audit->id);

        $this->assertSame('pending', $order->fresh()->status);
    }

    #[Test]
    public function facade_rollback_batch_reverts_all(): void
    {
        Recordkeeper::batch('facade-batch', function (): void {
            Order::create(['status' => 'x']);
            Order::create(['status' => 'y']);
        });

        Recordkeeper::rollbackBatch('facade-batch');

        $this->assertDatabaseCount('orders', 0);
    }

    // ------------------------------------------------------------------
    // resolveActorUsing
    // ------------------------------------------------------------------

    #[Test]
    public function resolve_actor_using_custom_resolver(): void
    {
        $manager = app(RecordkeeperManager::class);
        $manager->resolveActorUsing(fn () => (object) ['id' => 999, 'name' => 'bot']);

        $actor = $manager->resolveActor();
        $this->assertSame(999, $actor->id);
    }

    #[Test]
    public function resolve_actor_falls_back_to_auth_user(): void
    {
        $manager = app(RecordkeeperManager::class);
        $actor = $manager->resolveActor();

        $this->assertNull($actor); // no user authenticated in tests
    }

    #[Test]
    public function decorate_merges_json_string_context(): void
    {
        $manager = app(RecordkeeperManager::class);
        $manager->pushContext(['extra' => 'value']);

        $row = $manager->decorate([
            'context' => json_encode(['existing' => 'key']),
        ]);

        $this->assertSame('key', $row['context']['existing']);
        $this->assertSame('value', $row['context']['extra']);

        $manager->clearContext();
    }

    #[Test]
    public function current_tags_returns_empty_by_default(): void
    {
        $manager = app(RecordkeeperManager::class);
        $manager->withTags([]);

        $this->assertSame([], $manager->currentTags());
    }
}
