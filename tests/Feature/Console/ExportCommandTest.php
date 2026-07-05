<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Feature\Console;

use LaraArabDev\Recordkeeper\Support\AttributeResolver;
use LaraArabDev\Recordkeeper\Tests\Fixtures\Order;
use LaraArabDev\Recordkeeper\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('console')]
class ExportCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AttributeResolver::clearCache();
    }

    private function tempFile(): string
    {
        return sys_get_temp_dir().'/recordkeeper_export_test_'.uniqid();
    }

    #[Test]
    public function export_json(): void
    {
        Order::create(['status' => 'pending']);
        Order::create(['status' => 'shipped']);

        $file = $this->tempFile().'.json';

        $this->artisan('recordkeeper:export', [
            'file' => $file,
            '--format' => 'json',
        ])
            ->expectsOutputToContain('Exported')
            ->assertExitCode(0);

        $content = json_decode(file_get_contents($file), true);
        $this->assertCount(2, $content);

        @unlink($file);
    }

    #[Test]
    public function export_csv(): void
    {
        Order::create(['status' => 'pending']);

        $file = $this->tempFile().'.csv';

        $this->artisan('recordkeeper:export', [
            'file' => $file,
            '--format' => 'csv',
        ])
            ->expectsOutputToContain('Exported')
            ->assertExitCode(0);

        $lines = array_filter(explode("\n", file_get_contents($file)));
        $this->assertCount(2, $lines); // header + 1 data row

        @unlink($file);
    }

    #[Test]
    public function export_ndjson(): void
    {
        Order::create(['status' => 'pending']);
        Order::create(['status' => 'shipped']);

        $file = $this->tempFile().'.ndjson';

        $this->artisan('recordkeeper:export', [
            'file' => $file,
            '--format' => 'ndjson',
        ])
            ->expectsOutputToContain('Exported')
            ->assertExitCode(0);

        $lines = array_filter(explode("\n", file_get_contents($file)));
        $this->assertCount(2, $lines);

        foreach ($lines as $line) {
            $this->assertNotNull(json_decode($line, true));
        }

        @unlink($file);
    }

    #[Test]
    public function export_with_model_filter(): void
    {
        Order::create(['status' => 'pending']);

        $file = $this->tempFile().'.json';

        $this->artisan('recordkeeper:export', [
            'file' => $file,
            '--format' => 'json',
            '--model' => 'Order',
        ])
            ->expectsOutputToContain('Exported')
            ->assertExitCode(0);

        $content = json_decode(file_get_contents($file), true);
        $this->assertNotEmpty($content);

        @unlink($file);
    }

    #[Test]
    public function export_empty_result(): void
    {
        $file = $this->tempFile().'.json';

        $this->artisan('recordkeeper:export', [
            'file' => $file,
            '--format' => 'json',
            '--model' => 'NonExistent',
        ])
            ->expectsOutputToContain('Exported 0')
            ->assertExitCode(0);

        @unlink($file);
    }

    #[Test]
    public function export_unsupported_format(): void
    {
        $file = $this->tempFile().'.xml';

        $this->artisan('recordkeeper:export', [
            'file' => $file,
            '--format' => 'xml',
        ])
            ->expectsOutputToContain('Unsupported format')
            ->assertExitCode(1);
    }
}
