<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use LaraArabDev\Recordkeeper\Attributes\AuditEvent;
use LaraArabDev\Recordkeeper\Listeners\RecordEventAudit;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\AuditQuery;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[AuditEvent(tags: ['user'], capturePayload: true)]
class UserRegistered
{
    public function __construct(public readonly int $userId) {}

    public function toArray(): array
    {
        return ['user_id' => $this->userId];
    }
}

class NonAuditedLaravelEvent {}

class PlainObjectEvent
{
    public string $data = 'test';
}

#[AuditEvent(capturePayload: true)]
class CapturedPlainObjectEvent
{
    public int $value = 99;
}

#[AuditEvent(tags: ['user', 'auth'])]
class MultiTagEvent {}

/**
 * Feature tests for event-based auditing via the #[AuditEvent] attribute.
 */
#[Group('listeners')]
#[CoversClass(RecordEventAudit::class)]
final class EventAuditingTest extends TestCase
{
    #[Test]
    public function event_with_attribute_creates_audit(): void
    {
        event(new UserRegistered(42));

        $audit = Audit::where('event', 'event.UserRegistered')->first();

        $this->assertNotNull($audit);
        $this->assertSame('event', $audit->auditable_type);
        $this->assertSame(UserRegistered::class, $audit->context['event']);
    }

    #[Test]
    public function event_tags_are_stored(): void
    {
        event(new UserRegistered(42));

        $audit = Audit::where('event', 'event.UserRegistered')->first();

        $this->assertSame('user', $audit->tags);
    }

    #[Test]
    public function payload_is_captured_when_enabled(): void
    {
        event(new UserRegistered(42));

        $audit = Audit::where('event', 'event.UserRegistered')->first();

        $this->assertArrayHasKey('payload', $audit->context);
        $this->assertSame(42, $audit->context['payload'][0]['user_id']);
    }

    #[Test]
    public function event_without_attribute_is_not_audited(): void
    {
        event(new NonAuditedLaravelEvent);

        $this->assertSame(0, Audit::where('event', 'like', 'event.%')->count());
    }

    #[Test]
    public function event_listed_in_config_is_audited(): void
    {
        config(['recordkeeper.listen' => [NonAuditedLaravelEvent::class]]);

        event(new NonAuditedLaravelEvent);

        $this->assertSame(1, Audit::where('event', 'like', 'event.%')->count());
    }

    #[Test]
    public function illuminate_events_are_ignored(): void
    {
        event('Illuminate\\SomeInternalEvent', []);

        $this->assertSame(0, Audit::where('event', 'like', 'event.%')->count());
    }

    #[Test]
    public function model_scope_event_audits(): void
    {
        event(new UserRegistered(1));
        Audit::create(['event' => 'updated', 'auditable_type' => 'system', 'old_values' => [], 'new_values' => []]);

        $this->assertSame(1, Audit::eventAudits()->count());
    }

    #[Test]
    public function audit_query_events_method(): void
    {
        event(new UserRegistered(1));

        $results = app(AuditQuery::class)
            ->events()
            ->builder()
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('event.UserRegistered', $results->first()->event);
    }

    #[Test]
    public function payload_serializes_plain_object_via_get_object_vars(): void
    {
        config(['recordkeeper.listen' => [PlainObjectEvent::class]]);

        event(new PlainObjectEvent);

        $audit = Audit::where('event', 'event.PlainObjectEvent')->first();
        $this->assertNotNull($audit);
        $this->assertArrayNotHasKey('payload', $audit->context);
    }

    #[Test]
    public function config_listed_event_does_not_capture_payload(): void
    {
        config(['recordkeeper.listen' => [NonAuditedLaravelEvent::class]]);

        event(new NonAuditedLaravelEvent);

        $audit = Audit::where('event', 'like', 'event.%')->first();
        $this->assertNotNull($audit);
        $this->assertArrayNotHasKey('payload', $audit->context);
    }

    #[Test]
    public function event_multi_tags_stored_as_comma_separated(): void
    {
        event(new MultiTagEvent);

        $audit = Audit::where('event', 'event.MultiTagEvent')->first();

        $this->assertNotNull($audit);
        $this->assertSame('user,auth', $audit->tags);
    }

    #[Test]
    public function payload_serializes_plain_object_via_get_object_vars_with_capture(): void
    {
        event(new CapturedPlainObjectEvent);

        $audit = Audit::where('event', 'event.CapturedPlainObjectEvent')->first();

        $this->assertNotNull($audit);
        $this->assertArrayHasKey('payload', $audit->context);
        $this->assertSame(99, $audit->context['payload'][0]['value']);
    }

    #[Test]
    public function disabled_kill_switch_skips_event_audit(): void
    {
        config(['recordkeeper.enabled' => false]);

        event(new UserRegistered(42));

        $this->assertSame(0, Audit::where('event', 'like', 'event.%')->count());
    }

    #[Test]
    public function eloquent_prefix_events_are_ignored(): void
    {
        event('eloquent.created: App\Models\Order', []);

        $this->assertSame(0, Audit::where('event', 'like', 'event.%')->count());
    }
}
