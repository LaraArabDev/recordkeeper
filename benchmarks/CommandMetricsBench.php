<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Benchmarks;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use LaraArabDev\Recordkeeper\Listeners\RecordCommandAudit;
use LaraArabDev\Recordkeeper\Models\Audit;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

#[Groups(['command', 'metrics'])]
#[BeforeMethods(['setUp'])]
#[Warmup(2)]
#[Iterations(5)]
#[Revs(20)]
final class CommandMetricsBench extends BenchCase
{
    private StringInput $input;

    private NullOutput $output;

    public function setUp(): void
    {
        parent::setUp();

        config([
            'recordkeeper.commands.enabled' => true,
            'recordkeeper.commands.metrics.memory' => false,
            'recordkeeper.commands.metrics.audit_count' => false,
            'recordkeeper.commands.metrics.anomaly' => false,
        ]);

        $this->app['events']->subscribe(
            RecordCommandAudit::class
        );

        $this->input = new StringInput('');
        $this->output = new NullOutput;
    }

    public function setUpWithMetrics(): void
    {
        $this->setUp();
        config([
            'recordkeeper.commands.metrics.memory' => true,
            'recordkeeper.commands.metrics.audit_count' => true,
        ]);
    }

    public function setUpWithAnomaly(): void
    {
        $this->setUpWithMetrics();
        config([
            'recordkeeper.commands.metrics.anomaly' => true,
            'recordkeeper.commands.metrics.anomaly_min_runs' => 5,
        ]);

        $this->seedCommandHistory('bench:command', 5, 50, 2);
    }

    public function benchCommandWithoutMetrics(): void
    {
        event(new CommandStarting('bench:command', $this->input, $this->output));
        event(new CommandFinished('bench:command', $this->input, $this->output, 0));
    }

    #[BeforeMethods(['setUpWithMetrics'])]
    public function benchCommandWithMemoryAndAuditCount(): void
    {
        event(new CommandStarting('bench:command', $this->input, $this->output));
        event(new CommandFinished('bench:command', $this->input, $this->output, 0));
    }

    #[BeforeMethods(['setUpWithAnomaly'])]
    public function benchCommandWithAnomalyDetection(): void
    {
        event(new CommandStarting('bench:command', $this->input, $this->output));
        event(new CommandFinished('bench:command', $this->input, $this->output, 0));
    }

    #[BeforeMethods(['setUpWithMetrics'])]
    #[Revs(10)]
    public function benchCommandAuditCountWithLargeTable(): void
    {
        $this->seedAudits(1000);

        event(new CommandStarting('bench:command', $this->input, $this->output));

        $this->seedAudits(10);

        event(new CommandFinished('bench:command', $this->input, $this->output, 0));
    }

    private function seedCommandHistory(string $command, int $count, int $durationMs, int $auditCount): void
    {
        for ($i = 0; $i < $count; $i++) {
            Audit::create([
                'event' => 'command.finished',
                'auditable_type' => 'command',
                'old_values' => [],
                'new_values' => [],
                'context' => [
                    'command' => $command,
                    'exit_code' => 0,
                    'duration_ms' => $durationMs,
                    'audit_count' => $auditCount,
                    'memory_peak_mb' => 32.0,
                ],
            ]);
        }
    }
}
