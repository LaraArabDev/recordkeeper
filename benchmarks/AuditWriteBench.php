<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Benchmarks;

use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Models\AuditHttpRequest;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

#[Groups(['db', 'write'])]
#[BeforeMethods(['setUp'])]
#[Warmup(2)]
#[Iterations(5)]
#[Revs(20)]
final class AuditWriteBench extends BenchCase
{
    public function benchWriteAuditRow(): void
    {
        $audit = new Audit;
        $audit->fill([
            'event' => 'updated',
            'auditable_type' => 'App\\Models\\Order',
            'auditable_id' => 1,
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'paid'],
            'user_type' => null,
            'user_id' => null,
            'tags' => '',
            'context' => null,
        ]);
        $audit->save();
    }

    public function benchWriteAuditRowWithContext(): void
    {
        $audit = new Audit;
        $audit->fill([
            'event' => 'command.finished',
            'auditable_type' => 'command',
            'auditable_id' => null,
            'old_values' => [],
            'new_values' => [],
            'user_type' => null,
            'user_id' => null,
            'tags' => '',
            'context' => [
                'command' => 'import:orders',
                'exit_code' => 0,
                'duration_ms' => 1243,
                'memory_peak_mb' => 48.5,
                'audit_count' => 150,
            ],
        ]);
        $audit->save();
    }

    public function benchWriteHttpRequestRow(): void
    {
        AuditHttpRequest::create([
            'audit_id' => null,
            'method' => 'POST',
            'url' => 'https://api.stripe.com/v1/charges',
            'status_code' => 200,
            'duration_ms' => 312,
            'failed' => false,
            'created_at' => now(),
        ]);
    }

    public function benchWriteAuditAndLinkedHttpRequest(): void
    {
        $audit = new Audit;
        $audit->fill([
            'event' => 'job.processing',
            'auditable_type' => 'job',
            'auditable_id' => null,
            'old_values' => [],
            'new_values' => [],
            'user_type' => null,
            'user_id' => null,
            'tags' => '',
            'context' => ['job' => 'App\\Jobs\\ChargeStripe', 'queue' => 'default'],
        ]);
        $audit->save();

        AuditHttpRequest::create([
            'audit_id' => $audit->id,
            'method' => 'POST',
            'url' => 'https://api.stripe.com/v1/charges',
            'status_code' => 200,
            'duration_ms' => 412,
            'failed' => false,
            'created_at' => now(),
        ]);
    }
}

#[Groups(['db', 'query'])]
#[BeforeMethods(['setUp'])]
#[Warmup(2)]
#[Iterations(5)]
#[Revs(10)]
final class AuditQueryScalingBench extends BenchCase
{
    public function setUp100Rows(): void
    {
        parent::setUp();
        $this->seedAudits(100);
    }

    public function setUp1kRows(): void
    {
        parent::setUp();
        $this->seedAudits(1000);
    }

    public function setUp5kRows(): void
    {
        parent::setUp();
        $this->seedAudits(5000);
    }

    #[BeforeMethods(['setUp100Rows'])]
    public function benchMaxIdOn100Rows(): void
    {
        Audit::max('id');
    }

    #[BeforeMethods(['setUp1kRows'])]
    public function benchMaxIdOn1kRows(): void
    {
        Audit::max('id');
    }

    #[BeforeMethods(['setUp5kRows'])]
    public function benchMaxIdOn5kRows(): void
    {
        Audit::max('id');
    }

    #[BeforeMethods(['setUp100Rows'])]
    public function benchCountQueryOn100Rows(): void
    {
        Audit::where('event', '!=', 'command.finished')->count();
    }

    #[BeforeMethods(['setUp1kRows'])]
    public function benchCountQueryOn1kRows(): void
    {
        Audit::where('event', '!=', 'command.finished')->count();
    }

    #[BeforeMethods(['setUp5kRows'])]
    public function benchCountQueryOn5kRows(): void
    {
        Audit::where('event', '!=', 'command.finished')->count();
    }
}
