<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Console;

use Illuminate\Console\Command;

/**
 * Publish Recordkeeper and laravel-auditing config files and migrations.
 */
class InstallCommand extends Command
{
    protected $signature = 'recordkeeper:install {--force : Overwrite already published files}';

    protected $description = 'Install the Recordkeeper package (publish config + migrations)';

    /**
     * Publish config files and migrations for Recordkeeper and laravel-auditing.
     */
    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->info('Installing Recordkeeper...');

        if ($force || ! file_exists(config_path('recordkeeper.php'))) {
            $this->callSilently('vendor:publish', [
                '--provider' => 'LaraArabDev\\Recordkeeper\\RecordkeeperServiceProvider',
                '--tag' => 'recordkeeper-config',
                '--force' => $force,
            ]);
            $this->line('  <info>✓</info> Config published → config/recordkeeper.php');
        } else {
            $this->line('  <comment>→</comment> config/recordkeeper.php already exists, skipping (use --force to overwrite)');
        }

        if ($force || ! $this->recordkeeperMigrationExists()) {
            $this->callSilently('vendor:publish', [
                '--provider' => 'LaraArabDev\\Recordkeeper\\RecordkeeperServiceProvider',
                '--tag' => 'recordkeeper-migrations',
                '--force' => $force,
            ]);
            $this->line('  <info>✓</info> Extension migration published');
        } else {
            $this->line('  <comment>→</comment> Extension migration already exists, skipping (use --force to overwrite)');
        }

        if ($force || ! class_exists('CreateAuditsTable')) {
            $this->callSilently('vendor:publish', [
                '--provider' => 'OwenIt\\Auditing\\AuditingServiceProvider',
                '--tag' => 'migrations',
                '--force' => $force,
            ]);
            $this->line('  <info>✓</info> laravel-auditing migration published');
        } else {
            $this->line('  <comment>→</comment> laravel-auditing migration already exists, skipping');
        }

        if ($force || ! file_exists(config_path('audit.php'))) {
            $this->callSilently('vendor:publish', [
                '--provider' => 'OwenIt\\Auditing\\AuditingServiceProvider',
                '--tag' => 'config',
                '--force' => $force,
            ]);
            $this->line('  <info>✓</info> laravel-auditing config published → config/audit.php');
        } else {
            $this->line('  <comment>→</comment> config/audit.php already exists, skipping');
        }

        $this->newLine();

        $this->info('Quick start:');
        $this->line('');
        $this->line('  #[Auditable]');
        $this->line('  class Order extends Model implements \\OwenIt\\Auditing\\Contracts\\Auditable {');
        $this->line('      use \\LaraArabDev\\Recordkeeper\\Concerns\\AuditsChanges;');
        $this->line('  }');
        $this->line('');
        $this->line('  Route::middleware(\'audit.api\')->apiResource(\'orders\', OrderController::class);');

        $this->newLine();
        $this->line('Run <comment>php artisan migrate</comment> to apply the migration.');
        $this->info('Recordkeeper installed successfully!');

        return self::SUCCESS;
    }

    /**
     * Check if the Recordkeeper extension migration has already been published.
     */
    private function recordkeeperMigrationExists(): bool
    {
        return (bool) glob(database_path('migrations/*_add_recordkeeper_columns_to_audits_table.php'));
    }
}
