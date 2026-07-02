<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature;

use LaraArabDev\Recordkeeper\Actions\RedactValues;
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
 * Feature tests for redaction and encryption of sensitive attribute values.
 */
#[Group('privacy')]
#[CoversClass(RedactAttribute::class)]
#[CoversClass(EncryptAttribute::class)]
#[CoversClass(AttributeResolver::class)]
#[CoversClass(RedactValues::class)]
class PrivacyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    // ------------------------------------------------------------------
    // Redact modifier via #[Redact] attribute
    // ------------------------------------------------------------------

    #[Test]
    public function redacts_redact_flagged_attribute(): void
    {
        Order::create(['status' => 'pending', 'discount_code' => 'SAVE20']);

        $audit = Audit::where('event', 'created')->first();
        $modified = $audit->getModified();

        $this->assertSame('***', $modified['discount_code']['new'] ?? null);
    }

    #[Test]
    public function original_value_not_stored_for_redacted_field(): void
    {
        Order::create(['discount_code' => 'SECRET50']);

        $audit = Audit::first();
        $this->assertStringNotContainsString('SECRET50', json_encode($audit->new_values));
    }

    // ------------------------------------------------------------------
    // Pattern-based auto-redaction
    // ------------------------------------------------------------------

    #[Test]
    public function redacts_pattern_matched_password(): void
    {
        config(['recordkeeper.privacy.mode' => 'redact']);
        config(['recordkeeper.privacy.sensitive_patterns' => ['password']]);
        AttributeResolver::clearCache();

        Order::create(['status' => 'ok', 'password' => 'supersecret']);

        $audit = Audit::where('event', 'created')->first();
        $modified = $audit->getModified();

        $this->assertSame('***', $modified['password']['new'] ?? null);
    }

    #[Test]
    public function non_sensitive_fields_are_not_redacted(): void
    {
        Order::create(['status' => 'active', 'total' => 99.99]);

        $audit = Audit::first();
        $modified = $audit->getModified();

        // 'status' is not a sensitive field
        $this->assertSame('active', $modified['status']['new'] ?? null);
    }

    // ------------------------------------------------------------------
    // Encrypt modifier via #[Encrypt] attribute
    // ------------------------------------------------------------------

    #[Test]
    public function encrypts_encrypt_flagged_attribute(): void
    {
        Order::create(['national_id' => '123-45-6789']);

        $audit = Audit::where('event', 'created')->first();
        $modified = $audit->getModified();

        $this->assertTrue(EncryptAttribute::isEncrypted($modified['national_id']['new'] ?? ''));
    }

    #[Test]
    public function no_plaintext_in_encrypted_field(): void
    {
        Order::create(['national_id' => '987-65-4321']);

        $audit = Audit::first();
        $this->assertStringNotContainsString('987-65-4321', json_encode($audit->new_values));
    }

    #[Test]
    public function encrypted_value_is_decryptable(): void
    {
        Order::create(['national_id' => 'ABC-123']);

        $audit = Audit::first();
        $modified = $audit->getModified();
        $encrypted = $modified['national_id']['new'] ?? '';

        $this->assertSame('ABC-123', EncryptAttribute::decrypt($encrypted));
    }

    // ------------------------------------------------------------------
    // Privacy mode = off
    // ------------------------------------------------------------------

    #[Test]
    public function privacy_off_clears_all_modifiers(): void
    {
        config(['recordkeeper.privacy.mode' => 'off']);
        AttributeResolver::clearCache();

        $config = AttributeResolver::resolve(Order::class);

        $this->assertEmpty($config->attributeModifiers);
    }

    // ------------------------------------------------------------------
    // RedactValues action (used by middleware body sanitisation)
    // ------------------------------------------------------------------

    #[Test]
    public function redact_values_action_masks_sensitive_keys(): void
    {
        $action = app(RedactValues::class);

        $result = $action([
            'name' => 'John',
            'password' => 'my-secret',
            'token' => 'abc123',
            'email' => 'john@example.com',
        ]);

        $this->assertSame('John', $result['name']);
        $this->assertSame('***', $result['password']);
        $this->assertSame('***', $result['token']);
        $this->assertSame('john@example.com', $result['email']);
    }

    #[Test]
    public function redact_values_action_uses_custom_patterns(): void
    {
        $action = app(RedactValues::class);

        $result = $action(['secret_key' => 'value', 'name' => 'ok'], ['secret_key']);

        $this->assertSame('***', $result['secret_key']);
        $this->assertSame('ok', $result['name']);
    }
}
