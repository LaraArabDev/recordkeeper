<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit;

use LaraArabDev\Recordkeeper\DataObjects\AuditConfig;
use LaraArabDev\Recordkeeper\DataObjects\AuditPayload;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for AuditPayload and AuditConfig data objects.
 */
#[Group('data-objects')]
#[CoversClass(AuditPayload::class)]
#[CoversClass(AuditConfig::class)]
class DataObjectsTest extends TestCase
{
    #[Test]
    public function audit_payload_to_array_maps_all_fields(): void
    {
        $payload = new AuditPayload(
            event: 'created',
            auditableType: 'Order',
            auditableId: 42,
            oldValues: ['status' => 'a'],
            newValues: ['status' => 'b'],
            userType: 'App\Models\User',
            userId: 7,
            url: 'https://example.com/orders',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0',
            tags: 'finance',
            batchId: 'batch-1',
            context: ['reason' => 'test'],
            source: 'App\\Jobs\\SendEmail',
        );

        $arr = $payload->toArray();

        $this->assertSame('created', $arr['event']);
        $this->assertSame('Order', $arr['auditable_type']);
        $this->assertSame(42, $arr['auditable_id']);
        $this->assertSame(['status' => 'a'], $arr['old_values']);
        $this->assertSame(['status' => 'b'], $arr['new_values']);
        $this->assertSame('App\Models\User', $arr['user_type']);
        $this->assertSame(7, $arr['user_id']);
        $this->assertSame('https://example.com/orders', $arr['url']);
        $this->assertSame('127.0.0.1', $arr['ip_address']);
        $this->assertSame('Mozilla/5.0', $arr['user_agent']);
        $this->assertSame('finance', $arr['tags']);
        $this->assertSame('batch-1', $arr['batch_id']);
        $this->assertSame(['reason' => 'test'], $arr['context']);
        $this->assertSame('App\\Jobs\\SendEmail', $arr['source']);
    }

    #[Test]
    public function audit_payload_defaults_are_null(): void
    {
        $payload = new AuditPayload(
            event: 'created',
            auditableType: 'system',
            auditableId: null,
            oldValues: [],
            newValues: [],
        );

        $arr = $payload->toArray();

        $this->assertNull($arr['user_type']);
        $this->assertNull($arr['user_id']);
        $this->assertNull($arr['url']);
        $this->assertNull($arr['ip_address']);
        $this->assertNull($arr['user_agent']);
        $this->assertNull($arr['tags']);
        $this->assertNull($arr['batch_id']);
        $this->assertSame([], $arr['context']);
        $this->assertNull($arr['source']);
    }

    #[Test]
    public function audit_payload_from_array_reconstructs_source(): void
    {
        $data = [
            'event' => 'job.processed',
            'auditable_type' => 'job',
            'auditable_id' => null,
            'old_values' => [],
            'new_values' => [],
            'source' => 'App\\Jobs\\SendEmail',
        ];

        $payload = AuditPayload::fromArray($data);

        $this->assertSame('App\\Jobs\\SendEmail', $payload->source);
        $this->assertSame('App\\Jobs\\SendEmail', $payload->toArray()['source']);
    }

    #[Test]
    public function audit_config_stores_all_fields(): void
    {
        $config = new AuditConfig(
            auditInclude: ['name'],
            auditExclude: ['secret'],
            auditEvents: ['created', 'updated'],
            attributeModifiers: ['cvv' => 'RedactAttribute'],
            auditThreshold: 5,
            auditTags: ['billing'],
            retentionDays: 90,
        );

        $this->assertSame(['name'], $config->auditInclude);
        $this->assertSame(['secret'], $config->auditExclude);
        $this->assertSame(['created', 'updated'], $config->auditEvents);
        $this->assertSame(['cvv' => 'RedactAttribute'], $config->attributeModifiers);
        $this->assertSame(5, $config->auditThreshold);
        $this->assertSame(['billing'], $config->auditTags);
        $this->assertSame(90, $config->retentionDays);
    }
}
