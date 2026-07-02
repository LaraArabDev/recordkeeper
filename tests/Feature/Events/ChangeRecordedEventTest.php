<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use Illuminate\Support\Facades\Event;
use LaraArabDev\Recordkeeper\Events\ChangeRecorded;
use LaraArabDev\Recordkeeper\Facades\Recordkeeper;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for the ChangeRecorded event dispatch on audit creation.
 */
#[Group('events')]
#[CoversClass(ChangeRecorded::class)]
class ChangeRecordedEventTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function change_recorded_event_is_dispatched_on_log(): void
    {
        Event::fake([ChangeRecorded::class]);

        Recordkeeper::log('test.event', null, ['key' => 'value']);

        Event::assertDispatched(ChangeRecorded::class, function (ChangeRecorded $event): bool {
            return $event->audit->event === 'test.event';
        });
    }

    #[Test]
    public function change_recorded_event_carries_audit_instance(): void
    {
        Event::fake([ChangeRecorded::class]);

        Recordkeeper::log('audit.check');

        Event::assertDispatched(ChangeRecorded::class, function (ChangeRecorded $event): bool {
            return $event->audit !== null && $event->audit->id !== null;
        });
    }
}
