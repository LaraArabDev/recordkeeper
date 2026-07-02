<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit;

use LaraArabDev\Recordkeeper\Attributes\Audit;
use LaraArabDev\Recordkeeper\Attributes\Auditable;
use LaraArabDev\Recordkeeper\Attributes\AuditCommand;
use LaraArabDev\Recordkeeper\Attributes\AuditEvent;
use LaraArabDev\Recordkeeper\Attributes\AuditExclude;
use LaraArabDev\Recordkeeper\Attributes\AuditJob;
use LaraArabDev\Recordkeeper\Attributes\Encrypt;
use LaraArabDev\Recordkeeper\Attributes\Redact;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for PHP 8 audit attribute constructors and default values.
 */
#[Group('attributes')]
#[CoversClass(Auditable::class)]
#[CoversClass(Redact::class)]
#[CoversClass(Encrypt::class)]
#[CoversClass(AuditExclude::class)]
#[CoversClass(AuditCommand::class)]
#[CoversClass(AuditEvent::class)]
#[CoversClass(AuditJob::class)]
#[CoversClass(Audit::class)]
class AttributesTest extends TestCase
{
    #[Test]
    public function auditable_defaults(): void
    {
        $attr = new Auditable;

        $this->assertNull($attr->events);
        $this->assertSame([], $attr->only);
        $this->assertSame([], $attr->exclude);
        $this->assertSame([], $attr->redact);
        $this->assertSame([], $attr->encrypt);
        $this->assertNull($attr->retentionDays);
        $this->assertNull($attr->threshold);
        $this->assertSame([], $attr->tags);
    }

    #[Test]
    public function auditable_with_all_params(): void
    {
        $attr = new Auditable(
            events: ['created', 'updated'],
            only: ['name'],
            exclude: ['secret'],
            redact: ['cvv'],
            encrypt: ['nid'],
            retentionDays: 90,
            threshold: 10,
            tags: ['payments'],
        );

        $this->assertSame(['created', 'updated'], $attr->events);
        $this->assertSame(['name'], $attr->only);
        $this->assertSame(['secret'], $attr->exclude);
        $this->assertSame(['cvv'], $attr->redact);
        $this->assertSame(['nid'], $attr->encrypt);
        $this->assertSame(90, $attr->retentionDays);
        $this->assertSame(10, $attr->threshold);
        $this->assertSame(['payments'], $attr->tags);
    }

    #[Test]
    public function redact_stores_attributes(): void
    {
        $attr = new Redact('cvv', 'pan');

        $this->assertSame(['cvv', 'pan'], $attr->attributes);
    }

    #[Test]
    public function encrypt_stores_attributes(): void
    {
        $attr = new Encrypt('national_id', 'ssn');

        $this->assertSame(['national_id', 'ssn'], $attr->attributes);
    }

    #[Test]
    public function audit_exclude_stores_attributes(): void
    {
        $attr = new AuditExclude('internal_notes', 'debug');

        $this->assertSame(['internal_notes', 'debug'], $attr->attributes);
    }

    #[Test]
    public function audit_command_defaults(): void
    {
        $attr = new AuditCommand;

        $this->assertSame([], $attr->tags);
    }

    #[Test]
    public function audit_command_with_tags(): void
    {
        $attr = new AuditCommand(tags: ['billing']);

        $this->assertSame(['billing'], $attr->tags);
    }

    #[Test]
    public function audit_event_defaults(): void
    {
        $attr = new AuditEvent;

        $this->assertSame([], $attr->tags);
        $this->assertFalse($attr->capturePayload);
    }

    #[Test]
    public function audit_event_with_params(): void
    {
        $attr = new AuditEvent(tags: ['orders'], capturePayload: true);

        $this->assertSame(['orders'], $attr->tags);
        $this->assertTrue($attr->capturePayload);
    }

    #[Test]
    public function audit_job_defaults(): void
    {
        $attr = new AuditJob;

        $this->assertTrue($attr->queued);
        $this->assertTrue($attr->processing);
        $this->assertTrue($attr->processed);
        $this->assertTrue($attr->failed);
        $this->assertSame([], $attr->tags);
    }

    #[Test]
    public function audit_job_with_params(): void
    {
        $attr = new AuditJob(queued: false, processing: true, processed: false, failed: true, tags: ['jobs']);

        $this->assertFalse($attr->queued);
        $this->assertTrue($attr->processing);
        $this->assertFalse($attr->processed);
        $this->assertTrue($attr->failed);
        $this->assertSame(['jobs'], $attr->tags);
    }

    #[Test]
    public function audit_attribute_defaults(): void
    {
        $attr = new Audit;

        $this->assertNull($attr->tag);
        $this->assertFalse($attr->body);
        $this->assertFalse($attr->response);
        $this->assertSame(1.0, $attr->sample);
    }

    #[Test]
    public function audit_attribute_with_params(): void
    {
        $attr = new Audit(tag: 'payments', body: true, response: true, sample: 0.5);

        $this->assertSame('payments', $attr->tag);
        $this->assertTrue($attr->body);
        $this->assertTrue($attr->response);
        $this->assertSame(0.5, $attr->sample);
    }
}
