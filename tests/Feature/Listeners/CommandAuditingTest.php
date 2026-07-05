<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel;
use LaraArabDev\Recordkeeper\Concerns\AuditsCommand;
use LaraArabDev\Recordkeeper\Listeners\RecordCommandAudit;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class TraitAuditedCommand extends Command
{
    use AuditsCommand;

    protected $signature = 'test:trait-audited';

    public function handle(): int
    {
        return 0;
    }
}

class TraitAuditedCommandTagged extends Command
{
    use AuditsCommand;

    protected $signature = 'test:trait-audited-tagged';

    public function auditCommandTags(): array
    {
        return ['maintenance'];
    }

    public function handle(): int
    {
        return 0;
    }
}

/**
 * Feature tests for Artisan command auditing via the #[AuditCommand] attribute.
 */
#[Group('listeners')]
#[CoversClass(RecordCommandAudit::class)]
final class CommandAuditingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('recordkeeper.commands.enabled', true);
    }

    #[Test]
    public function command_finished_creates_audit(): void
    {
        $this->fireCommand('cache:clear', 0);

        $audit = Audit::where('event', 'command.finished')->first();

        $this->assertNotNull($audit);
        $this->assertSame('command', $audit->auditable_type);
        $this->assertSame('cache:clear', $audit->context['command']);
        $this->assertSame(0, $audit->context['exit_code']);
        $this->assertSame('cache:clear', $audit->source);
    }

    #[Test]
    public function command_duration_is_recorded(): void
    {
        $this->fireCommand('cache:clear', 0);

        $audit = Audit::where('event', 'command.finished')->first();

        $this->assertArrayHasKey('duration_ms', $audit->context);
        $this->assertIsInt($audit->context['duration_ms']);
    }

    #[Test]
    public function excluded_command_is_not_audited(): void
    {
        $this->fireCommand('schedule:run', 0);

        $this->assertSame(0, Audit::where('event', 'command.finished')->count());
    }

    #[Test]
    public function custom_excluded_command_is_not_audited(): void
    {
        config(['recordkeeper.commands.exclude' => ['cache:clear']]);

        $this->fireCommand('cache:clear', 0);

        $this->assertSame(0, Audit::where('event', 'command.finished')->count());
    }

    #[Test]
    public function command_not_audited_when_disabled(): void
    {
        config(['recordkeeper.commands.enabled' => false]);

        $this->fireCommand('cache:clear', 0);

        $this->assertSame(0, Audit::where('event', 'command.finished')->count());
    }

    #[Test]
    public function model_scope_command_audits(): void
    {
        $this->fireCommand('cache:clear', 0);
        Audit::create(['event' => 'updated', 'auditable_type' => 'system', 'old_values' => [], 'new_values' => []]);

        $this->assertSame(1, Audit::commandAudits()->count());
    }

    #[Test]
    public function memory_peak_is_recorded(): void
    {
        $this->fireCommand('cache:clear', 0);

        $audit = Audit::where('event', 'command.finished')->first();

        $this->assertArrayHasKey('memory_peak_mb', $audit->context);
        $this->assertIsFloat($audit->context['memory_peak_mb']);
        $this->assertGreaterThan(0, $audit->context['memory_peak_mb']);
    }

    #[Test]
    public function audit_count_is_recorded(): void
    {
        $this->fireCommand('cache:clear', 0);

        $audit = Audit::where('event', 'command.finished')->first();

        $this->assertArrayHasKey('audit_count', $audit->context);
        $this->assertIsInt($audit->context['audit_count']);
    }

    #[Test]
    public function memory_not_recorded_when_disabled(): void
    {
        config(['recordkeeper.commands.metrics.memory' => false]);

        $this->fireCommand('cache:clear', 0);

        $audit = Audit::where('event', 'command.finished')->first();

        $this->assertArrayNotHasKey('memory_peak_mb', $audit->context);
    }

    #[Test]
    public function audit_count_not_recorded_when_disabled(): void
    {
        config(['recordkeeper.commands.metrics.audit_count' => false]);

        $this->fireCommand('cache:clear', 0);

        $audit = Audit::where('event', 'command.finished')->first();

        $this->assertArrayNotHasKey('audit_count', $audit->context);
    }

    #[Test]
    public function non_zero_exit_code_is_recorded(): void
    {
        $this->fireCommand('cache:clear', 1);

        $audit = Audit::where('event', 'command.finished')->first();

        $this->assertSame(1, $audit->context['exit_code']);
    }

    #[Test]
    public function audit_count_counts_only_audits_created_during_command(): void
    {
        Audit::create([
            'event' => 'updated',
            'auditable_type' => 'system',
            'old_values' => [],
            'new_values' => [],
        ]);

        $input = new StringInput('');
        $output = new NullOutput;

        event(new CommandStarting('cache:clear', $input, $output));

        Audit::create(['event' => 'created', 'auditable_type' => 'system', 'old_values' => [], 'new_values' => []]);
        Audit::create(['event' => 'updated', 'auditable_type' => 'system', 'old_values' => [], 'new_values' => []]);

        event(new CommandFinished('cache:clear', $input, $output, 0));

        $audit = Audit::where('event', 'command.finished')->first();

        $this->assertSame(2, $audit->context['audit_count']);
    }

    #[Test]
    public function audit_count_excludes_command_finished_events(): void
    {
        $input = new StringInput('');
        $output = new NullOutput;

        event(new CommandStarting('cache:clear', $input, $output));

        Audit::create(['event' => 'command.finished', 'auditable_type' => 'command', 'old_values' => [], 'new_values' => [], 'context' => ['command' => 'other:cmd'], 'source' => 'other:cmd']);
        Audit::create(['event' => 'updated', 'auditable_type' => 'system', 'old_values' => [], 'new_values' => []]);

        event(new CommandFinished('cache:clear', $input, $output, 0));

        $audit = Audit::where('event', 'command.finished')->where('source', 'cache:clear')->first();

        $this->assertSame(1, $audit->context['audit_count']);
    }

    #[Test]
    public function audit_count_is_zero_when_no_audits_created_during_command(): void
    {
        $this->fireCommand('cache:clear', 0);

        $audit = Audit::where('event', 'command.finished')->first();

        $this->assertSame(0, $audit->context['audit_count']);
    }

    #[Test]
    public function anomaly_flagged_when_audit_count_spikes(): void
    {
        config([
            'recordkeeper.commands.metrics.anomaly' => true,
            'recordkeeper.commands.metrics.anomaly_min_runs' => 3,
            'recordkeeper.commands.metrics.anomaly_multiplier' => 1.5,
        ]);

        foreach (range(1, 3) as $i) {
            Audit::create([
                'event' => 'command.finished',
                'auditable_type' => 'command',
                'old_values' => [],
                'new_values' => [],
                'context' => ['command' => 'cache:clear', 'exit_code' => 0, 'duration_ms' => 10, 'audit_count' => 1], 'source' => 'cache:clear',
            ]);
        }

        $input = new StringInput('');
        $output = new NullOutput;

        event(new CommandStarting('cache:clear', $input, $output));

        foreach (range(1, 3) as $i) {
            Audit::create(['event' => 'updated', 'auditable_type' => 'system', 'old_values' => [], 'new_values' => []]);
        }

        event(new CommandFinished('cache:clear', $input, $output, 0));

        $audit = Audit::where('event', 'command.finished')
            ->where('id', '>', 3)
            ->latest()
            ->first();

        $this->assertTrue($audit->context['anomaly'] ?? false);
        $this->assertArrayHasKey('anomaly_reason', $audit->context);
        $this->assertStringContainsString('audit_count', $audit->context['anomaly_reason']);
        $this->assertStringContainsString('3 >', $audit->context['anomaly_reason']);
        $this->assertStringContainsString('avg (1)', $audit->context['anomaly_reason']);
        $this->assertStringNotContainsString('duration', $audit->context['anomaly_reason']);
    }

    #[Test]
    public function anomaly_not_flagged_when_no_spike(): void
    {
        config([
            'recordkeeper.commands.metrics.anomaly' => true,
            'recordkeeper.commands.metrics.anomaly_min_runs' => 3,
            'recordkeeper.commands.metrics.anomaly_multiplier' => 100.0,
        ]);

        foreach (range(1, 3) as $i) {
            Audit::create([
                'event' => 'command.finished',
                'auditable_type' => 'command',
                'old_values' => [],
                'new_values' => [],
                'context' => ['command' => 'cache:clear', 'exit_code' => 0, 'duration_ms' => 10, 'audit_count' => 100], 'source' => 'cache:clear',
            ]);
        }

        $this->fireCommand('cache:clear', 0);

        $recent = Audit::where('event', 'command.finished')->latest()->first();

        $this->assertArrayNotHasKey('anomaly', $recent->context);
    }

    #[Test]
    public function anomaly_not_flagged_without_enough_history(): void
    {
        config([
            'recordkeeper.commands.metrics.anomaly' => true,
            'recordkeeper.commands.metrics.anomaly_min_runs' => 5,
        ]);

        $this->fireCommand('cache:clear', 0);

        $audit = Audit::where('event', 'command.finished')->first();

        $this->assertArrayNotHasKey('anomaly', $audit->context);
    }

    #[Test]
    public function audit_count_anomaly_not_flagged_when_avg_audit_count_is_zero(): void
    {
        config([
            'recordkeeper.commands.metrics.anomaly' => true,
            'recordkeeper.commands.metrics.anomaly_min_runs' => 3,
            'recordkeeper.commands.metrics.anomaly_multiplier' => 1.5,
        ]);

        foreach (range(1, 3) as $i) {
            Audit::create([
                'event' => 'command.finished',
                'auditable_type' => 'command',
                'old_values' => [],
                'new_values' => [],
                'context' => ['command' => 'cache:clear', 'exit_code' => 0, 'duration_ms' => 10, 'audit_count' => 0], 'source' => 'cache:clear',
            ]);
        }

        $input = new StringInput('');
        $output = new NullOutput;

        event(new CommandStarting('cache:clear', $input, $output));

        Audit::create(['event' => 'updated', 'auditable_type' => 'system', 'old_values' => [], 'new_values' => []]);

        event(new CommandFinished('cache:clear', $input, $output, 0));

        $recent = Audit::where('event', 'command.finished')->latest()->first();

        $this->assertArrayNotHasKey('anomaly', $recent->context);
    }

    #[Test]
    public function duration_anomaly_not_flagged_when_avg_duration_is_zero(): void
    {
        config([
            'recordkeeper.commands.metrics.anomaly' => true,
            'recordkeeper.commands.metrics.anomaly_min_runs' => 3,
            'recordkeeper.commands.metrics.anomaly_multiplier' => 1.5,
        ]);

        foreach (range(1, 3) as $i) {
            Audit::create([
                'event' => 'command.finished',
                'auditable_type' => 'command',
                'old_values' => [],
                'new_values' => [],
                'context' => ['command' => 'cache:clear', 'exit_code' => 0, 'duration_ms' => 0, 'audit_count' => 0], 'source' => 'cache:clear',
            ]);
        }

        $this->fireCommand('cache:clear', 0);

        $recent = Audit::where('event', 'command.finished')->latest()->first();

        $this->assertArrayNotHasKey('anomaly', $recent->context);
    }

    #[Test]
    public function anomaly_not_flagged_when_disabled(): void
    {
        config(['recordkeeper.commands.metrics.anomaly' => false]);

        foreach (range(1, 5) as $i) {
            Audit::create([
                'event' => 'command.finished',
                'auditable_type' => 'command',
                'old_values' => [],
                'new_values' => [],
                'context' => ['command' => 'cache:clear', 'exit_code' => 0, 'duration_ms' => 10, 'audit_count' => 0], 'source' => 'cache:clear',
            ]);
        }

        $this->fireCommand('cache:clear', 0);

        $recent = Audit::where('event', 'command.finished')->latest()->first();

        $this->assertArrayNotHasKey('anomaly', $recent->context);
    }

    #[Test]
    public function trait_opts_command_into_auditing(): void
    {
        config(['recordkeeper.commands.enabled' => false]);
        $this->app->make(Kernel::class)->registerCommand(new TraitAuditedCommand);

        $this->fireCommand('test:trait-audited', 0);

        $audit = Audit::where('event', 'command.finished')
            ->where('source', 'test:trait-audited')
            ->first();

        $this->assertNotNull($audit);
    }

    #[Test]
    public function trait_custom_tags_are_stored_on_command(): void
    {
        config(['recordkeeper.commands.enabled' => false]);
        $this->app->make(Kernel::class)->registerCommand(new TraitAuditedCommandTagged);

        $this->fireCommand('test:trait-audited-tagged', 0);

        $audit = Audit::where('event', 'command.finished')
            ->where('source', 'test:trait-audited-tagged')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('maintenance', $audit->tags);
    }

    private function fireCommand(string $command, int $exitCode): void
    {
        $input = new StringInput('');
        $output = new NullOutput;

        event(new CommandStarting($command, $input, $output));
        event(new CommandFinished($command, $input, $output, $exitCode));
    }
}
