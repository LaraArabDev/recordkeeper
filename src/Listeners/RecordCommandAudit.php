<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Listeners;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Events\Dispatcher;
use LaraArabDev\Recordkeeper\Actions\RecordAudit;
use LaraArabDev\Recordkeeper\Attributes\AuditCommand;
use LaraArabDev\Recordkeeper\Concerns\AuditsCommand;
use LaraArabDev\Recordkeeper\Concerns\ResolvesHttpFilterConfig;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\HttpTracker;

/**
 * Subscribe to Artisan command lifecycle events and record audit entries.
 *
 * Captures exit code, duration, peak memory, audit count, and optional
 * anomaly detection based on historical averages.
 */
final class RecordCommandAudit
{
    use ResolvesHttpFilterConfig;

    /** @var array<string, float> */
    private static array $startTimes = [];

    /** @var array<string, int> */
    private static array $startMaxIds = [];

    public function __construct(private readonly RecordAudit $recordAudit) {}

    /** @return array<string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            CommandStarting::class => 'onStarting',
            CommandFinished::class => 'onFinished',
        ];
    }

    /**
     * Capture the start time and baseline audit ID for a command.
     */
    public function onStarting(CommandStarting $event): void
    {
        if (! $this->shouldAudit($event->command)) {
            return;
        }

        self::$startTimes[$event->command] = microtime(true);
        self::$startMaxIds[$event->command] = (int) (Audit::max('id') ?? 0);

        if (config('recordkeeper.http.enabled', false)) {
            $commandClass = $this->resolveCommandClass($event->command);
            $filterConfig = $commandClass ? $this->resolveHttpFilterConfig($commandClass) : null;

            // Use audit_id=0 since the audit is created in onFinished
            app(HttpTracker::class)->setContext(0, $filterConfig);
        }
    }

    /**
     * Record an audit when a command finishes, with metrics and optional anomaly detection.
     */
    public function onFinished(CommandFinished $event): void
    {
        if (config('recordkeeper.http.enabled', false)) {
            app(HttpTracker::class)->clearContext();
        }

        if (! $this->shouldAudit($event->command)) {
            return;
        }

        $duration = isset(self::$startTimes[$event->command])
            ? (int) ((microtime(true) - self::$startTimes[$event->command]) * 1000)
            : null;

        $startMaxId = self::$startMaxIds[$event->command] ?? null;

        unset(self::$startTimes[$event->command], self::$startMaxIds[$event->command]);

        $commandClass = $this->resolveCommandClass($event->command);
        $attr = $commandClass ? $this->attribute($commandClass) : null;

        $context = array_filter([
            'command' => $event->command,
            'exit_code' => $event->exitCode,
            'duration_ms' => $duration,
        ], fn ($v) => $v !== null);

        if (config('recordkeeper.commands.metrics.memory', true)) {
            $context['memory_peak_mb'] = round(memory_get_peak_usage(true) / 1048576, 2);
        }

        if (config('recordkeeper.commands.metrics.audit_count', true) && $startMaxId !== null) {
            $context['audit_count'] = Audit::where('id', '>', $startMaxId)
                ->where('event', '!=', 'command.finished')
                ->count();
        }

        if (config('recordkeeper.commands.metrics.anomaly', false) && $duration !== null) {
            $anomalyData = $this->detectAnomaly($event->command, $duration, $context['audit_count'] ?? null);

            if ($anomalyData !== null) {
                $context = array_merge($context, $anomalyData);
            }
        }

        $tags = $this->resolveTags($commandClass, $attr);

        ($this->recordAudit)(new AuditPayload(
            event: 'command.finished',
            auditableType: 'command',
            auditableId: null,
            oldValues: [],
            newValues: [],
            tags: implode(',', $tags),
            context: $context,
            source: $event->command,
        ));
    }

    /**
     * Determine whether the given command should be audited.
     */
    private function shouldAudit(?string $command): bool
    {
        if (! config('recordkeeper.enabled', true)) {
            return false;
        }

        if ($command === null) {
            return false;
        }

        $excluded = config('recordkeeper.commands.exclude', []);
        if (in_array($command, $excluded, true)) {
            return false;
        }

        if (config('recordkeeper.commands.enabled', false)) {
            return true;
        }

        $commandClass = $this->resolveCommandClass($command);

        return $commandClass && ($this->attribute($commandClass) !== null || $this->usesTrait($commandClass));
    }

    /**
     * Compare current run metrics against historical averages to detect anomalies.
     *
     * @return array{anomaly: true, anomaly_reason: string}|null
     */
    private function detectAnomaly(string $commandName, int $duration, ?int $auditCount): ?array
    {
        $minRuns = (int) config('recordkeeper.commands.metrics.anomaly_min_runs', 5);
        $multiplier = (float) config('recordkeeper.commands.metrics.anomaly_multiplier', 2.0);

        $history = Audit::commandAudits()
            ->where('event', 'command.finished')
            ->where('source', $commandName)
            ->latest()
            ->limit($minRuns)
            ->get();

        if ($history->count() < $minRuns) {
            return null;
        }

        $avgDuration = $history->avg(fn ($a) => $a->context['duration_ms'] ?? 0);
        $avgAudits = $history->avg(fn ($a) => $a->context['audit_count'] ?? 0);

        $reasons = [];

        if ($avgDuration > 0 && $duration > $avgDuration * $multiplier) {
            $reasons[] = sprintf('duration %dms > %.0fx avg (%.0fms)', $duration, $multiplier, $avgDuration);
        }

        if ($auditCount !== null && $avgAudits > 0 && $auditCount > $avgAudits * $multiplier) {
            $reasons[] = sprintf('audit_count %d > %.0fx avg (%.0f)', $auditCount, $multiplier, $avgAudits);
        }

        if (empty($reasons)) {
            return null;
        }

        return [
            'anomaly' => true,
            'anomaly_reason' => implode('; ', $reasons),
        ];
    }

    /**
     * Resolve the command class name from an Artisan command name.
     *
     * @return class-string|null
     */
    private function resolveCommandClass(string $commandName): ?string
    {
        $artisan = app(Kernel::class);

        if (! method_exists($artisan, 'all')) {
            return null;
        }

        $commands = $artisan->all();

        foreach ($commands as $name => $command) {
            if ($name === $commandName) {
                return $command::class;
            }
        }

        return null;
    }

    /**
     * Resolve tags from attribute, trait, or empty default.
     *
     * Priority: attribute > trait > empty.
     *
     * @return list<string>
     */
    private function resolveTags(?string $commandClass, ?AuditCommand $attr): array
    {
        if ($attr !== null) {
            return $attr->tags;
        }

        if ($commandClass !== null && $this->usesTrait($commandClass)) {
            $instance = (new \ReflectionClass($commandClass))->newInstanceWithoutConstructor();

            return $instance->auditCommandTags();
        }

        return [];
    }

    /**
     * Check whether the given class uses the AuditsCommand trait.
     *
     * @param  class-string  $commandClass
     */
    private function usesTrait(string $commandClass): bool
    {
        if (! class_exists($commandClass)) {
            return false;
        }

        return in_array(AuditsCommand::class, class_uses_recursive($commandClass), true);
    }

    /**
     * Retrieve the #[AuditCommand] attribute instance from a command class, if present.
     *
     * @param  class-string  $commandClass
     */
    private function attribute(string $commandClass): ?AuditCommand
    {
        $attrs = (new \ReflectionClass($commandClass))->getAttributes(AuditCommand::class);

        return $attrs ? $attrs[0]->newInstance() : null;
    }
}
