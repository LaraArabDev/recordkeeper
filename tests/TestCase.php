<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LaraArabDev\Recordkeeper\Models\Audit;
use LaraArabDev\Recordkeeper\RecordkeeperServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use OwenIt\Auditing\AuditingServiceProvider;

/**
 * Base test case for Recordkeeper with in-memory SQLite database and package providers.
 */
abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            AuditingServiceProvider::class,
            RecordkeeperServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('recordkeeper.queue.enabled', false);
        $app['config']->set('recordkeeper.strict', true);
        $app['config']->set('audit.implementation', Audit::class);
        $app['config']->set('audit.console', true);
    }

    private function setUpDatabase(): void
    {
        // laravel-auditing's base audits table
        Schema::create('audits', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->index(['user_type', 'user_id']); // already done by laravel-auditing's nullableMorphs
            $table->string('event');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->index(['auditable_type', 'auditable_id']); // already done by laravel-auditing's morphs
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Recordkeeper extension columns
            $table->string('guard', 30)->nullable()->index();
            $table->string('batch_id', 100)->nullable();
            $table->json('context')->nullable();
            $table->string('source', 255)->nullable()->index();
            $table->index('event');
            $table->index('created_at');

            // Performance indexes
            $table->index('deleted_at');
            $table->index('user_id');
            $table->index(['batch_id', 'event']);
            $table->index(['event', 'created_at']);
            $table->index(['auditable_type', 'auditable_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id', 'event'], 'audits_auditable_event_index');
        });

        Schema::create('audit_http_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('audit_id')->nullable()->index();
            $table->string('method', 10)->index();
            $table->text('url');
            $table->string('host', 255)->nullable()->index();
            $table->integer('status_code')->nullable()->index();
            $table->integer('duration_ms')->nullable();
            $table->boolean('failed')->default(false)->index();
            $table->text('exception')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('response_headers')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('audit_tag', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('audit_id');
            $table->foreign('audit_id')->references('id')->on('audits')->cascadeOnDelete();
            $table->string('tag', 100)->index();
            $table->index(['audit_id', 'tag']);
        });

        // Test orders table
        Schema::create('orders', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('status')->default('pending');
            $table->decimal('total', 10, 2)->default(0);
            $table->string('discount_code')->nullable();
            $table->string('password')->nullable();
            $table->string('national_id')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
