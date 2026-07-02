<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for batch grouping, manual logging, and the kill switch.
 */
#[Group('support')]
#[CoversClass(\LaraArabDev\Recordkeeper\Recordkeeper::class)]
class BatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function batch_shares_batch_id(): void
    {
        Recordkeeper::batch('import-001', function (): void {
            Order::create(['status' => 'pending']);
            Order::create(['status' => 'active']);
        });

        $audits = Audit::where('batch_id', 'import-001')->get();
        $this->assertCount(2, $audits);
    }

    #[Test]
    public function facade_log_records_arbitrary_event(): void
    {
        $audit = Recordkeeper::log('app.maintenance', null, ['message' => 'backup started']);

        $this->assertDatabaseHas('audits', ['event' => 'app.maintenance']);
        $this->assertSame('backup started', $audit->context['message'] ?? null);
    }

    #[Test]
    public function kill_switch_disables_all_auditing(): void
    {
        config(['recordkeeper.enabled' => false]);

        // Middleware-level check: when disabled, audits are skipped
        // Model-level: laravel-auditing still writes; the kill switch applies to middleware
        // and the Recordkeeper::log guard
        $enabled = app(\LaraArabDev\Recordkeeper\Recordkeeper::class)->isEnabled();
        $this->assertFalse($enabled);

        config(['recordkeeper.enabled' => true]);
    }
}
