<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use LaraArabDev\Recordkeeper\Concerns\AuditsChanges;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Modifiers\EncryptAttribute;
use LaraArabDev\Recordkeeper\Modifiers\RedactAttribute;
use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests verifying sensitive values are never stored as plaintext in audit records.
 */
#[Group('models')]
#[CoversClass(AuditsChanges::class)]
#[CoversClass(RedactAttribute::class)]
#[CoversClass(EncryptAttribute::class)]
class RedactionInvariantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    #[Test]
    public function discount_code_never_stored_in_plaintext_across_many_creates(): void
    {
        $codes = array_map(fn (int $i) => "SAVE{$i}-SECRET-".uniqid(), range(1, 50));

        foreach ($codes as $code) {
            Order::create(['status' => 'pending', 'discount_code' => $code]);
        }

        $allAuditValues = Audit::pluck('new_values')
            ->map(fn ($v) => json_encode($v))
            ->implode('');

        foreach ($codes as $code) {
            $this->assertStringNotContainsString(
                $code,
                $allAuditValues,
                "Plaintext discount_code '{$code}' leaked into audit records",
            );
        }
    }

    #[Test]
    public function national_id_never_stored_in_plaintext_across_many_creates(): void
    {
        $ids = array_map(fn (int $i) => "NID-{$i}-".uniqid(), range(1, 50));

        foreach ($ids as $id) {
            Order::create(['national_id' => $id]);
        }

        $allAuditValues = Audit::pluck('new_values')
            ->map(fn ($v) => json_encode($v))
            ->implode('');

        foreach ($ids as $id) {
            $this->assertStringNotContainsString(
                $id,
                $allAuditValues,
                "Plaintext national_id '{$id}' leaked into audit records",
            );
        }
    }

    #[Test]
    public function no_plaintext_leak_across_interleaved_creates_and_updates(): void
    {
        $sensitiveValues = [];

        for ($i = 1; $i <= 20; $i++) {
            $code = "PROMO{$i}-".uniqid();
            $nid = "NID{$i}-".uniqid();
            $newCode = "UPDATED{$i}-".uniqid();
            $newNid = "UPDNID{$i}-".uniqid();

            array_push($sensitiveValues, $code, $nid, $newCode, $newNid);

            $order = Order::create([
                'status' => 'pending',
                'discount_code' => $code,
                'national_id' => $nid,
            ]);

            $order->update([
                'discount_code' => $newCode,
                'national_id' => $newNid,
            ]);
        }

        $allAuditValues = Audit::get()
            ->flatMap(fn ($a) => [json_encode($a->new_values), json_encode($a->old_values)])
            ->implode('');

        foreach ($sensitiveValues as $value) {
            $this->assertStringNotContainsString(
                $value,
                $allAuditValues,
                "Sensitive value '{$value}' leaked into audit records",
            );
        }
    }

    #[Test]
    public function excluded_field_never_appears_in_any_audit(): void
    {
        $notes = 'top-secret-internal-note-'.uniqid();

        $order = Order::create(['status' => 'ok', 'internal_notes' => $notes]);
        $order->update(['internal_notes' => 'changed-'.uniqid()]);
        $order->delete();

        $allAuditValues = Audit::get()
            ->flatMap(fn ($a) => [json_encode($a->new_values), json_encode($a->old_values)])
            ->implode('');

        $this->assertStringNotContainsString(
            $notes,
            $allAuditValues,
            '#[AuditExclude] field leaked into audit records',
        );
    }
}
