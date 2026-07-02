<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Benchmarks;

use LaraArabDev\Recordkeeper\Support\HttpTracker;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[Groups(['http', 'tracker', 'pure-php'])]
#[BeforeMethods(['setUp'])]
#[Warmup(3)]
#[Iterations(5)]
final class HttpTrackerBench
{
    private HttpTracker $tracker;

    public function setUp(): void
    {
        $this->tracker = new HttpTracker;
    }

    #[Revs(10000)]
    public function benchContextSetClear(): void
    {
        $this->tracker->setContext(42);
        $this->tracker->clearContext();
    }

    #[Revs(10000)]
    public function benchContextRead(): void
    {
        $this->tracker->setContext(42);
        $this->tracker->currentAuditId();
    }

    #[Revs(5000)]
    public function benchStartAndFinishSingleRequest(): void
    {
        $obj = new \stdClass;
        $this->tracker->startRequest($obj, microtime(true));
        $this->tracker->finishRequest($obj, microtime(true));
    }

    #[Revs(1000)]
    public function benchFinishUnknownRequest(): void
    {
        $obj = new \stdClass;
        $result = $this->tracker->finishRequest($obj, microtime(true));
        unset($result);
    }

    #[Revs(500)]
    public function benchTenConcurrentRequests(): void
    {
        $objects = [];
        $start = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            $obj = new \stdClass;
            $objects[] = $obj;
            $this->tracker->startRequest($obj, $start);
        }

        $end = microtime(true);
        foreach ($objects as $obj) {
            $this->tracker->finishRequest($obj, $end);
        }
    }
}
