<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit\Concerns;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use LaraArabDev\Recordkeeper\Attributes\TrackHttp;
use LaraArabDev\Recordkeeper\Concerns\AuditsChanges;
use LaraArabDev\Recordkeeper\Concerns\AuditsCommand;
use LaraArabDev\Recordkeeper\Concerns\AuditsEvent;
use LaraArabDev\Recordkeeper\Concerns\AuditsJob;
use LaraArabDev\Recordkeeper\Concerns\ResolvesHttpFilterConfig;
use LaraArabDev\Recordkeeper\Concerns\TracksOutboundHttp;
use LaraArabDev\Recordkeeper\DataObjects\HttpFilterConfig;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Recordkeeper;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

// --- Fixture classes for TracksOutboundHttp ---

class HttpTrackingJob
{
    use TracksOutboundHttp;
}

class HttpTrackingJobDisabled
{
    use TracksOutboundHttp;

    public function shouldTrackHttp(): bool
    {
        return false;
    }
}

class HttpTrackingJobWithInclude
{
    use TracksOutboundHttp;

    public function httpIncludeHosts(): array
    {
        return ['api.stripe.com', 'api.github.com'];
    }
}

class HttpTrackingJobWithExclude
{
    use TracksOutboundHttp;

    public function httpExcludeHosts(): array
    {
        return ['localhost', 'internal.local'];
    }
}

// --- Fixture classes for ResolvesHttpFilterConfig ---

#[TrackHttp(enabled: true, includeHosts: ['api.example.com'])]
class AttributeTrackedJob {}

#[TrackHttp(enabled: false)]
class AttributeDisabledJob {}

class TraitTrackedJob
{
    use TracksOutboundHttp;

    public function httpExcludeHosts(): array
    {
        return ['skip.local'];
    }
}

#[TrackHttp(includeHosts: ['attr-wins.com'])]
class BothAttributeAndTraitJob
{
    use TracksOutboundHttp;

    public function httpIncludeHosts(): array
    {
        return ['trait-loses.com'];
    }
}

class PlainJobNoTracking {}

// --- Fixture classes for AuditsJob toggle overrides ---

class TraitJobProcessingDisabled
{
    use AuditsJob;

    public function shouldAuditProcessing(): bool
    {
        return false;
    }
}

class TraitJobProcessedDisabled
{
    use AuditsJob;

    public function shouldAuditProcessed(): bool
    {
        return false;
    }
}

class TraitJobFailedDisabled
{
    use AuditsJob;

    public function shouldAuditFailed(): bool
    {
        return false;
    }
}

class TraitJobAllDisabled
{
    use AuditsJob;

    public function shouldAuditQueued(): bool
    {
        return false;
    }

    public function shouldAuditProcessing(): bool
    {
        return false;
    }

    public function shouldAuditProcessed(): bool
    {
        return false;
    }

    public function shouldAuditFailed(): bool
    {
        return false;
    }
}

// --- Helper trait to expose resolveHttpFilterConfig ---

class HttpFilterResolver
{
    use ResolvesHttpFilterConfig;

    public function resolve(string $className): ?HttpFilterConfig
    {
        return $this->resolveHttpFilterConfig($className);
    }
}

// --- Fixture for AuditsCommand default ---

class DefaultAuditsCommandClass
{
    use AuditsCommand;
}

// --- Fixture for AuditsEvent defaults ---

class DefaultAuditsEventClass
{
    use AuditsEvent;
}

/**
 * Tests for all Concern traits: AuditsChanges, AuditsJob, AuditsCommand,
 * AuditsEvent, TracksOutboundHttp, and ResolvesHttpFilterConfig.
 */
#[Group('concerns')]
#[CoversClass(AuditsChanges::class)]
#[CoversClass(AuditsJob::class)]
#[CoversClass(AuditsCommand::class)]
#[CoversClass(AuditsEvent::class)]
#[CoversClass(TracksOutboundHttp::class)]
#[CoversClass(ResolvesHttpFilterConfig::class)]
final class ConcernsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
        app(Recordkeeper::class)->clearContext();
    }

    // ====================================================================
    // AuditsChanges — transformAudit edge cases
    // ====================================================================

    #[Test]
    public function transform_audit_skips_redaction_when_privacy_mode_off(): void
    {
        config(['recordkeeper.privacy.mode' => 'off']);

        $order = Order::create(['status' => 'pending', 'password' => 'secret123']);

        $audit = Audit::first();
        // password is globally excluded, but let's test with a pattern-matching field
        // Create a model audit with a sensitive field name
        $data = $order->transformAudit([
            'new_values' => ['api_key' => 'abc123'],
            'old_values' => [],
        ]);

        $this->assertSame('abc123', $data['new_values']['api_key']);
    }

    #[Test]
    public function transform_audit_skips_redaction_when_patterns_empty(): void
    {
        config(['recordkeeper.privacy.sensitive_patterns' => []]);

        $order = new Order;
        $order->initializeAuditsChanges();

        $data = $order->transformAudit([
            'new_values' => ['api_key' => 'abc123'],
            'old_values' => [],
        ]);

        $this->assertSame('abc123', $data['new_values']['api_key']);
    }

    #[Test]
    public function transform_audit_skips_non_array_values(): void
    {
        $order = new Order;
        $order->initializeAuditsChanges();

        $data = $order->transformAudit([
            'new_values' => null,
            'old_values' => 'not-an-array',
        ]);

        $this->assertNull($data['new_values']);
        $this->assertSame('not-an-array', $data['old_values']);
    }

    #[Test]
    public function transform_audit_skips_fields_with_attribute_modifiers(): void
    {
        // Order has #[Encrypt('national_id')] which sets an attributeModifier
        $order = new Order;
        $order->initializeAuditsChanges();

        // national_id has an attributeModifier (Encrypt), so redaction should skip it
        $data = $order->transformAudit([
            'new_values' => ['national_id' => 'sensitive-id-value'],
            'old_values' => [],
        ]);

        // Should NOT be redacted because attributeModifiers handles it
        $this->assertSame('sensitive-id-value', $data['new_values']['national_id']);
    }

    #[Test]
    public function transform_audit_redacts_matching_pattern_in_old_values(): void
    {
        $order = new Order;
        $order->initializeAuditsChanges();

        $data = $order->transformAudit([
            'new_values' => [],
            'old_values' => ['api_key' => 'old-key', 'name' => 'John'],
        ]);

        $this->assertSame('***', $data['old_values']['api_key']);
        $this->assertSame('John', $data['old_values']['name']);
    }

    #[Test]
    public function audit_context_returns_model_instance(): void
    {
        $order = new Order;

        $result = $order->auditContext(['reason' => 'test']);

        $this->assertSame($order, $result);
    }

    #[Test]
    public function audit_context_pushes_context_to_recordkeeper(): void
    {
        $order = new Order;
        $order->auditContext(['reason' => 'admin-fix']);

        Order::create(['status' => 'pending']);

        $audit = Audit::first();
        $this->assertSame('admin-fix', $audit->context['reason'] ?? null);
    }

    #[Test]
    public function generate_tags_includes_global_tags(): void
    {
        app(Recordkeeper::class)->withTags(['global-tag']);

        $order = new Order;
        $tags = $order->generateTags();

        $this->assertContains('global-tag', $tags);
    }

    #[Test]
    public function initialize_runs_attribute_resolver_and_creates_audit(): void
    {
        // initializeAuditsChanges is called during model construction
        // It resolves config from attributes — the proof is that the model
        // correctly excludes 'internal_notes' (via #[AuditExclude]) in audits
        $order = Order::create(['status' => 'pending', 'internal_notes' => 'private']);

        $audit = Audit::first();
        $modified = $audit->getModified();

        $this->assertArrayNotHasKey('internal_notes', $modified);
        $this->assertArrayHasKey('status', $modified);
    }

    // ====================================================================
    // TracksOutboundHttp — trait defaults and overrides
    // ====================================================================

    #[Test]
    public function tracks_outbound_http_defaults_enabled(): void
    {
        $job = new HttpTrackingJob;

        $this->assertTrue($job->shouldTrackHttp());
    }

    #[Test]
    public function tracks_outbound_http_defaults_empty_include(): void
    {
        $job = new HttpTrackingJob;

        $this->assertSame([], $job->httpIncludeHosts());
    }

    #[Test]
    public function tracks_outbound_http_defaults_empty_exclude(): void
    {
        $job = new HttpTrackingJob;

        $this->assertSame([], $job->httpExcludeHosts());
    }

    #[Test]
    public function tracks_outbound_http_can_disable(): void
    {
        $job = new HttpTrackingJobDisabled;

        $this->assertFalse($job->shouldTrackHttp());
    }

    #[Test]
    public function tracks_outbound_http_custom_include_hosts(): void
    {
        $job = new HttpTrackingJobWithInclude;

        $this->assertSame(['api.stripe.com', 'api.github.com'], $job->httpIncludeHosts());
    }

    #[Test]
    public function tracks_outbound_http_custom_exclude_hosts(): void
    {
        $job = new HttpTrackingJobWithExclude;

        $this->assertSame(['localhost', 'internal.local'], $job->httpExcludeHosts());
    }

    // ====================================================================
    // ResolvesHttpFilterConfig — attribute, trait, neither, both
    // ====================================================================

    #[Test]
    public function resolves_filter_config_from_attribute(): void
    {
        $resolver = new HttpFilterResolver;
        $config = $resolver->resolve(AttributeTrackedJob::class);

        $this->assertNotNull($config);
        $this->assertTrue($config->enabled);
        $this->assertSame(['api.example.com'], $config->includeHosts);
    }

    #[Test]
    public function resolves_filter_config_disabled_attribute(): void
    {
        $resolver = new HttpFilterResolver;
        $config = $resolver->resolve(AttributeDisabledJob::class);

        $this->assertNotNull($config);
        $this->assertFalse($config->enabled);
        $this->assertFalse($config->allowsHost('any.com'));
    }

    #[Test]
    public function resolves_filter_config_from_trait(): void
    {
        $resolver = new HttpFilterResolver;
        $config = $resolver->resolve(TraitTrackedJob::class);

        $this->assertNotNull($config);
        $this->assertTrue($config->enabled);
        $this->assertSame(['skip.local'], $config->excludeHosts);
    }

    #[Test]
    public function resolves_null_when_no_attribute_or_trait(): void
    {
        $resolver = new HttpFilterResolver;
        $config = $resolver->resolve(PlainJobNoTracking::class);

        $this->assertNull($config);
    }

    #[Test]
    public function resolves_null_for_nonexistent_class(): void
    {
        $resolver = new HttpFilterResolver;
        $config = $resolver->resolve('NonExistent\\ClassName\\That\\Does\\Not\\Exist');

        $this->assertNull($config);
    }

    #[Test]
    public function attribute_takes_priority_over_trait_in_resolver(): void
    {
        $resolver = new HttpFilterResolver;
        $config = $resolver->resolve(BothAttributeAndTraitJob::class);

        $this->assertNotNull($config);
        $this->assertSame(['attr-wins.com'], $config->includeHosts);
        $this->assertNotContains('trait-loses.com', $config->includeHosts);
    }

    // ====================================================================
    // AuditsJob — trait toggle overrides via lifecycle events
    // ====================================================================

    #[Test]
    public function trait_processing_disabled_skips_processing_event(): void
    {
        $job = $this->mockQueueJob(TraitJobProcessingDisabled::class);
        event(new JobProcessing('sync', $job));

        $this->assertSame(0, Audit::where('event', 'job.processing')->count());
    }

    #[Test]
    public function trait_processed_disabled_skips_processed_event(): void
    {
        $job = $this->mockQueueJob(TraitJobProcessedDisabled::class);
        event(new JobProcessed('sync', $job));

        $this->assertSame(0, Audit::where('event', 'job.processed')->count());
    }

    #[Test]
    public function trait_failed_disabled_skips_failed_event(): void
    {
        $job = $this->mockQueueJob(TraitJobFailedDisabled::class);
        event(new JobFailed('sync', $job, new \RuntimeException('error')));

        $this->assertSame(0, Audit::where('event', 'job.failed')->count());
    }

    #[Test]
    public function trait_all_toggles_disabled_skips_all_events(): void
    {
        $job = new TraitJobAllDisabled;
        event(new JobQueued('sync', 'default', 'id', $job, [], null));

        $mock = $this->mockQueueJob(TraitJobAllDisabled::class);
        event(new JobProcessing('sync', $mock));
        event(new JobProcessed('sync', $mock));
        event(new JobFailed('sync', $mock, new \RuntimeException('error')));

        $this->assertSame(0, Audit::where('event', 'like', 'job.%')->count());
    }

    #[Test]
    public function trait_defaults_all_toggles_true(): void
    {
        $job = new HttpTrackingJob;
        // HttpTrackingJob uses TracksOutboundHttp, not AuditsJob, so create one with AuditsJob
        $auditableJob = new class
        {
            use AuditsJob;
        };

        $this->assertTrue($auditableJob->shouldAuditQueued());
        $this->assertTrue($auditableJob->shouldAuditProcessing());
        $this->assertTrue($auditableJob->shouldAuditProcessed());
        $this->assertTrue($auditableJob->shouldAuditFailed());
        $this->assertSame([], $auditableJob->auditJobTags());
    }

    // ====================================================================
    // AuditsCommand — trait defaults
    // ====================================================================

    #[Test]
    public function audits_command_default_tags_empty(): void
    {
        $cmd = new DefaultAuditsCommandClass;

        $this->assertSame([], $cmd->auditCommandTags());
    }

    // ====================================================================
    // AuditsEvent — trait defaults
    // ====================================================================

    #[Test]
    public function audits_event_default_tags_empty(): void
    {
        $evt = new DefaultAuditsEventClass;

        $this->assertSame([], $evt->auditEventTags());
    }

    #[Test]
    public function audits_event_default_capture_payload_false(): void
    {
        $evt = new DefaultAuditsEventClass;

        $this->assertFalse($evt->shouldCapturePayload());
    }

    // ====================================================================
    // HttpFilterConfig — allowsHost logic
    // ====================================================================

    #[Test]
    public function filter_config_disabled_rejects_all(): void
    {
        $config = new HttpFilterConfig(enabled: false);

        $this->assertFalse($config->allowsHost('anything.com'));
    }

    #[Test]
    public function filter_config_include_hosts_allows_only_listed(): void
    {
        $config = new HttpFilterConfig(includeHosts: ['api.stripe.com']);

        $this->assertTrue($config->allowsHost('api.stripe.com'));
        $this->assertFalse($config->allowsHost('other.com'));
    }

    #[Test]
    public function filter_config_exclude_hosts_blocks_listed(): void
    {
        $config = new HttpFilterConfig(excludeHosts: ['localhost']);

        $this->assertFalse($config->allowsHost('localhost'));
        $this->assertTrue($config->allowsHost('api.example.com'));
    }

    #[Test]
    public function filter_config_no_filters_allows_all(): void
    {
        $config = new HttpFilterConfig;

        $this->assertTrue($config->allowsHost('anything.com'));
    }

    #[Test]
    public function filter_config_include_takes_precedence_over_exclude(): void
    {
        $config = new HttpFilterConfig(
            includeHosts: ['api.stripe.com'],
            excludeHosts: ['api.stripe.com'],
        );

        // Include check runs first; stripe.com is in include list so it's allowed
        $this->assertTrue($config->allowsHost('api.stripe.com'));
        // Other hosts not in include list are rejected
        $this->assertFalse($config->allowsHost('other.com'));
    }

    // ====================================================================
    // Helpers
    // ====================================================================

    private function mockQueueJob(string $jobClass): Job
    {
        $mock = $this->createMock(Job::class);
        $mock->method('getName')->willReturn($jobClass);
        $mock->method('getRawBody')->willReturn(json_encode(['displayName' => $jobClass]));
        $mock->method('getQueue')->willReturn('default');
        $mock->method('attempts')->willReturn(1);

        return $mock;
    }
}
