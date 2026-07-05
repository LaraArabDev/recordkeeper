<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit\Actions;

use LaraArabDev\Recordkeeper\Actions\TailAudits;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('actions')]
#[CoversClass(TailAudits::class)]
class TailAuditsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function latest_id_returns_zero_when_no_audits(): void
    {
        $tailer = app(TailAudits::class);

        $this->assertSame(0, $tailer->latestId());
    }

    #[Test]
    public function latest_id_returns_max_audit_id(): void
    {
        Order::create(['status' => 'pending']);
        $maxId = (int) Audit::max('id');

        $tailer = app(TailAudits::class);

        $this->assertSame($maxId, $tailer->latestId());
    }

    #[Test]
    public function poll_returns_audits_after_given_id(): void
    {
        Order::create(['status' => 'first']);
        $lastId = (int) Audit::max('id');

        Order::create(['status' => 'second']);

        $tailer = app(TailAudits::class);
        $results = $tailer->poll($lastId);

        $this->assertCount(1, $results);
    }

    #[Test]
    public function poll_returns_empty_when_no_new_audits(): void
    {
        Order::create(['status' => 'pending']);
        $lastId = (int) Audit::max('id');

        $tailer = app(TailAudits::class);
        $results = $tailer->poll($lastId);

        $this->assertCount(0, $results);
    }

    #[Test]
    public function poll_filters_by_model(): void
    {
        $lastId = 0;

        Order::create(['status' => 'pending']);

        $tailer = app(TailAudits::class);
        $results = $tailer->poll($lastId, 'Order');

        $this->assertGreaterThanOrEqual(1, $results->count());
        foreach ($results as $audit) {
            $this->assertStringContainsString('Order', $audit->auditable_type);
        }
    }

    #[Test]
    public function poll_filters_by_event(): void
    {
        $lastId = 0;

        $order = Order::create(['status' => 'pending']);
        $order->update(['status' => 'shipped']);

        $tailer = app(TailAudits::class);
        $results = $tailer->poll($lastId, null, 'created');

        foreach ($results as $audit) {
            $this->assertSame('created', $audit->event);
        }
    }

    #[Test]
    public function poll_filters_by_guard(): void
    {
        Audit::create([
            'event' => 'created',
            'auditable_type' => 'Order',
            'auditable_id' => 1,
            'old_values' => [],
            'new_values' => ['status' => 'ok'],
            'guard' => 'admin',
        ]);

        $tailer = app(TailAudits::class);
        $results = $tailer->poll(0, null, null, 'admin');

        $this->assertCount(1, $results);
        $this->assertSame('admin', $results->first()->guard);
    }
}
