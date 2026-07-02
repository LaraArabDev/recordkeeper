<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Unit;

use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\Support\TerminalRenderer;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for TerminalRenderer: table, JSON, CSV, NDJSON output and color-coded diff rendering.
 */
#[Group('support')]
#[CoversClass(TerminalRenderer::class)]
class TerminalRendererTest extends TestCase
{
    private function savedAudit(array $attrs = []): Audit
    {
        $audit = new Audit;
        $audit->fill(array_merge([
            'event' => 'created',
            'auditable_type' => Order::class,
            'auditable_id' => 1,
            'old_values' => [],
            'new_values' => ['status' => 'pending'],
            'user_type' => null,
            'user_id' => null,
            'batch_id' => null,
            'context' => [],
        ], $attrs));
        $audit->save();

        return $audit;
    }

    #[Test]
    public function table_outputs_empty_message_when_no_rows(): void
    {
        ob_start();
        TerminalRenderer::table(['ID', 'Event'], []);
        $output = ob_get_clean();

        $this->assertStringContainsString('No results found.', $output);
    }

    #[Test]
    public function table_renders_headers_and_data_rows(): void
    {
        ob_start();
        TerminalRenderer::table(['ID', 'Event'], [
            ['id' => 1, 'event' => 'created'],
            ['id' => 2, 'event' => 'updated'],
        ]);
        $output = ob_get_clean();

        $this->assertStringContainsString('ID', $output);
        $this->assertStringContainsString('Event', $output);
        $this->assertStringContainsString('created', $output);
        $this->assertStringContainsString('updated', $output);
        $this->assertStringContainsString('+', $output);
        $this->assertStringContainsString('|', $output);
    }

    #[Test]
    public function table_pads_all_rows_to_same_width(): void
    {
        ob_start();
        TerminalRenderer::table(['H'], [
            ['cell' => 'short'],
            ['cell' => 'a_very_long_cell_value'],
        ]);
        $output = ob_get_clean();

        $lines = array_values(array_filter(explode("\n", $output)));
        $firstWidth = strlen($lines[0]);
        foreach ($lines as $line) {
            if (str_starts_with($line, '+') || str_starts_with($line, '|')) {
                $this->assertSame($firstWidth, strlen($line));
            }
        }
    }

    #[Test]
    public function json_outputs_pretty_printed_json(): void
    {
        ob_start();
        TerminalRenderer::json(['key' => 'value', 'num' => 42]);
        $output = ob_get_clean();

        $this->assertStringContainsString('"key"', $output);
        $this->assertStringContainsString('"value"', $output);
        $this->assertStringContainsString('42', $output);
        $this->assertGreaterThan(1, substr_count($output, "\n"));
    }

    #[Test]
    public function ndjson_outputs_one_object_per_line(): void
    {
        ob_start();
        TerminalRenderer::ndjson([['a' => 1], ['b' => 2]]);
        $output = ob_get_clean();

        $lines = array_values(array_filter(explode("\n", $output)));
        $this->assertCount(2, $lines);
        $this->assertSame(['a' => 1], json_decode($lines[0], true));
        $this->assertSame(['b' => 2], json_decode($lines[1], true));
    }

    #[Test]
    public function csv_outputs_header_and_data_rows(): void
    {
        ob_start();
        TerminalRenderer::csv(['ID', 'Event'], [['id' => 1, 'event' => 'created']]);
        $output = ob_get_clean();

        $this->assertStringContainsString('ID', $output);
        $this->assertStringContainsString('Event', $output);
        $this->assertStringContainsString('created', $output);
    }

    #[Test]
    public function diff_outputs_no_changes_label_when_nothing_modified(): void
    {
        $audit = $this->savedAudit(['old_values' => [], 'new_values' => []]);

        ob_start();
        TerminalRenderer::diff($audit);
        $output = ob_get_clean();

        $this->assertStringContainsString('no attribute changes', $output);
    }

    #[Test]
    public function diff_shows_old_and_new_values_per_attribute(): void
    {
        $audit = $this->savedAudit([
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'shipped'],
        ]);

        ob_start();
        TerminalRenderer::diff($audit);
        $output = ob_get_clean();

        $this->assertStringContainsString('status', $output);
        $this->assertStringContainsString('pending', $output);
        $this->assertStringContainsString('shipped', $output);
        $this->assertStringContainsString("\033[", $output);
    }

    #[Test]
    public function diff_formats_null_old_value_as_null_label(): void
    {
        $audit = $this->savedAudit([
            'old_values' => ['field' => null],
            'new_values' => ['field' => 'value'],
        ]);

        ob_start();
        TerminalRenderer::diff($audit);
        $output = ob_get_clean();

        $this->assertStringContainsString('(null)', $output);
    }

    #[Test]
    public function diff_formats_redacted_marker_as_redacted_label(): void
    {
        $audit = $this->savedAudit([
            'old_values' => ['code' => 'REAL'],
            'new_values' => ['code' => '***'],
        ]);

        ob_start();
        TerminalRenderer::diff($audit);
        $output = ob_get_clean();

        $this->assertStringContainsString('(redacted/encrypted)', $output);
    }

    #[Test]
    public function diff_formats_encrypted_prefix_as_redacted_label(): void
    {
        $audit = $this->savedAudit([
            'old_values' => ['nid' => 'PLAIN'],
            'new_values' => ['nid' => '__encrypted:abc123'],
        ]);

        ob_start();
        TerminalRenderer::diff($audit);
        $output = ob_get_clean();

        $this->assertStringContainsString('(redacted/encrypted)', $output);
    }

    #[Test]
    public function audit_to_row_maps_system_actor_and_subject(): void
    {
        $audit = $this->savedAudit([
            'auditable_id' => 5,
            'user_type' => null,
            'user_id' => null,
            'batch_id' => null,
            'new_values' => ['status' => 'ok'],
        ]);

        $row = TerminalRenderer::auditToRow($audit);

        $this->assertSame($audit->id, $row['id']);
        $this->assertSame('created', $row['event']);
        $this->assertStringContainsString('Order', $row['subject']);
        $this->assertStringContainsString('#5', $row['subject']);
        $this->assertSame('system', $row['actor']);
        $this->assertSame('', $row['batch']);
    }

    #[Test]
    public function audit_to_row_maps_batch_and_changed_keys(): void
    {
        $audit = $this->savedAudit([
            'event' => 'updated',
            'batch_id' => 'batch-abc',
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'shipped'],
        ]);

        $row = TerminalRenderer::auditToRow($audit);

        $this->assertSame('batch-abc', $row['batch']);
        $this->assertStringContainsString('status', $row['changed']);
        $this->assertNotSame('', $row['created']);
    }

    #[Test]
    public function audit_to_row_formats_actor_with_type_and_id(): void
    {
        $audit = $this->savedAudit([
            'user_type' => Order::class,
            'user_id' => 42,
            'new_values' => ['status' => 'ok'],
        ]);

        $row = TerminalRenderer::auditToRow($audit);

        $this->assertSame('Order #42', $row['actor']);
    }

    #[Test]
    public function audit_to_row_formats_actor_with_null_type_defaults_to_user(): void
    {
        $audit = $this->savedAudit([
            'user_type' => null,
            'user_id' => 7,
            'new_values' => ['status' => 'ok'],
        ]);

        $row = TerminalRenderer::auditToRow($audit);

        $this->assertSame('User #7', $row['actor']);
    }

    #[Test]
    public function audit_to_row_created_returns_empty_string_when_created_at_is_null(): void
    {
        $audit = \Mockery::mock(Audit::class)->makePartial();
        $audit->shouldReceive('getModified')->andReturn([]);
        $audit->forceFill([
            'id' => 1,
            'event' => 'created',
            'auditable_type' => 'system',
            'auditable_id' => null,
            'user_type' => null,
            'user_id' => null,
            'batch_id' => null,
            'created_at' => null,
        ]);

        $row = TerminalRenderer::auditToRow($audit);

        $this->assertSame('', $row['created']);
    }

    #[Test]
    public function diff_formats_array_value_as_json(): void
    {
        $audit = new Audit;
        $audit->forceFill([
            'event' => 'updated',
            'auditable_type' => 'system',
            'auditable_id' => null,
            'old_values' => ['tags' => ['a', 'b']],
            'new_values' => ['tags' => ['a', 'b', 'c']],
            'user_type' => null,
            'user_id' => null,
            'batch_id' => null,
            'context' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ob_start();
        TerminalRenderer::diff($audit);
        $output = ob_get_clean();

        $this->assertStringContainsString('["a","b"]', $output);
        $this->assertStringContainsString('["a","b","c"]', $output);
    }
}
