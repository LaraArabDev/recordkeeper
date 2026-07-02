<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit;

use LaraArabDev\Recordkeeper\Support\HttpTracker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for HttpTracker context management and request duration tracking.
 */
#[Group('support')]
#[CoversClass(HttpTracker::class)]
final class HttpTrackerTest extends TestCase
{
    private HttpTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tracker = new HttpTracker;
    }

    #[Test]
    public function initial_audit_id_is_null(): void
    {
        $this->assertNull($this->tracker->currentAuditId());
    }

    #[Test]
    public function set_context_stores_audit_id(): void
    {
        $this->tracker->setContext(42);

        $this->assertSame(42, $this->tracker->currentAuditId());
    }

    #[Test]
    public function clear_context_resets_to_null(): void
    {
        $this->tracker->setContext(42);
        $this->tracker->clearContext();

        $this->assertNull($this->tracker->currentAuditId());
    }

    #[Test]
    public function set_context_overwrites_previous_value(): void
    {
        $this->tracker->setContext(1);
        $this->tracker->setContext(99);

        $this->assertSame(99, $this->tracker->currentAuditId());
    }

    #[Test]
    public function finish_request_returns_duration(): void
    {
        $obj = new \stdClass;
        $start = microtime(true);
        usleep(5000);
        $end = microtime(true);

        $this->tracker->startRequest($obj, $start);
        $result = $this->tracker->finishRequest($obj, $end);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('duration_ms', $result);
        $this->assertGreaterThan(0, $result['duration_ms']);
        $this->assertIsInt($result['duration_ms']);
    }

    #[Test]
    public function finish_unknown_request_returns_null(): void
    {
        $obj = new \stdClass;
        $result = $this->tracker->finishRequest($obj, microtime(true));

        $this->assertNull($result);
    }

    #[Test]
    public function finish_request_cleans_up_pending_entry(): void
    {
        $obj = new \stdClass;
        $this->tracker->startRequest($obj, microtime(true));
        $this->tracker->finishRequest($obj, microtime(true));

        $result = $this->tracker->finishRequest($obj, microtime(true));

        $this->assertNull($result);
    }

    #[Test]
    public function multiple_requests_tracked_independently(): void
    {
        $obj1 = new \stdClass;
        $obj2 = new \stdClass;

        $this->tracker->startRequest($obj1, 1000.0);
        $this->tracker->startRequest($obj2, 1000.5);

        $result1 = $this->tracker->finishRequest($obj1, 1001.0);
        $result2 = $this->tracker->finishRequest($obj2, 1002.0);

        $this->assertSame(1000, $result1['duration_ms']);
        $this->assertSame(1500, $result2['duration_ms']);
    }

    #[Test]
    public function duration_computed_in_milliseconds(): void
    {
        $obj = new \stdClass;
        $this->tracker->startRequest($obj, 1000.000);
        $result = $this->tracker->finishRequest($obj, 1000.123);

        $this->assertSame(123, $result['duration_ms']);
    }

    #[Test]
    public function zero_duration_when_start_equals_end(): void
    {
        $obj = new \stdClass;
        $time = microtime(true);
        $this->tracker->startRequest($obj, $time);
        $result = $this->tracker->finishRequest($obj, $time);

        $this->assertSame(0, $result['duration_ms']);
    }
}
