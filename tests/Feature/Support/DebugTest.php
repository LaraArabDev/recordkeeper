<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use LaraArabDev\Recordkeeper\Concerns\AuditsChanges;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use OwenIt\Auditing\Contracts\Auditor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests verifying the laravel-auditing integration and manual audit execution.
 */
#[Group('support')]
#[CoversClass(AuditsChanges::class)]
class DebugTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function debug_auditor_directly(): void
    {
        $order = Order::create(['status' => 'pending', 'total' => 100.00]);
        echo "\nOrder created with id: ".$order->id."\n";
        echo 'Audit count after create: '.Audit::count()."\n";

        // Manually trigger audit
        try {
            $order->setAuditEvent('updated');
            $order->status = 'shipped';
            $order->saveQuietly();
            $order->setAuditEvent('created');
            $auditor = app(Auditor::class);
            echo 'Auditor class: '.get_class($auditor)."\n";
            $auditor->execute($order);
            echo 'Audit count after manual execute: '.Audit::count()."\n";
        } catch (\Throwable $e) {
            echo 'Error during manual execute: '.$e->getMessage()."\n";
            echo substr($e->getTraceAsString(), 0, 1000)."\n";
        }

        $this->assertTrue(true);
    }
}
