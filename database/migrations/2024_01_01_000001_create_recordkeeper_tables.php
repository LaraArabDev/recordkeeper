<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audits')) {
            $this->createAuditsTable();
        } else {
            $this->extendAuditsTable();
        }

        Schema::create('audit_http_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_id')->nullable()->index()->constrained('audits')->nullOnDelete();
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
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('audit_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_id')->constrained('audits')->cascadeOnDelete();
            $table->string('tag', 100)->index();
            $table->index(['audit_id', 'tag']);
        });
    }

    private function createAuditsTable(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $morphPrefix = config('audit.user.morph_prefix', 'user');

        Schema::connection($connection)->create('audits', function (Blueprint $table) use ($morphPrefix): void {
            $table->bigIncrements('id');
            $table->string($morphPrefix.'_type')->nullable();
            $table->unsignedBigInteger($morphPrefix.'_id')->nullable();
            $table->string('event');
            $table->morphs('auditable');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->string('tags')->nullable();
            $table->string('guard', 30)->nullable();
            $table->string('batch_id', 100)->nullable();
            $table->json('context')->nullable();
            $table->string('source', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index([$morphPrefix.'_id', $morphPrefix.'_type']);
            $table->index('event');
            $table->index('guard');
            $table->index('created_at');
            $table->index('deleted_at');
            $table->index($morphPrefix.'_id');
            $table->index('source');
            $table->index(['batch_id', 'event']);
            $table->index(['event', 'created_at']);
            $table->index(['auditable_type', 'auditable_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id', 'event'], 'audits_auditable_event_index');
        });
    }

    private function extendAuditsTable(): void
    {
        Schema::table('audits', function (Blueprint $table): void {
            $table->string('guard', 30)->nullable()->after('tags');
            $table->string('batch_id', 100)->nullable()->after('guard');
            $table->json('context')->nullable()->after('batch_id');
            $table->string('source', 255)->nullable()->after('context');
            $table->softDeletes();

            $table->index('event');
            $table->index('guard');
            $table->index('created_at');
            $table->index('deleted_at');
            $table->index('user_id');
            $table->index('source');
            $table->index(['batch_id', 'event']);
            $table->index(['event', 'created_at']);
            $table->index(['auditable_type', 'auditable_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id', 'event'], 'audits_auditable_event_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_tag');
        Schema::dropIfExists('audit_http_requests');

        Schema::table('audits', function (Blueprint $table): void {
            $table->dropIndex([
                'audits_event_index',
                'audits_guard_index',
                'audits_created_at_index',
                'audits_deleted_at_index',
                'audits_user_id_index',
                'audits_source_index',
                'audits_batch_id_event_index',
                'audits_event_created_at_index',
                'audits_auditable_type_auditable_id_created_at_index',
                'audits_auditable_event_index',
            ]);
            $table->dropColumn(['guard', 'batch_id', 'context', 'source', 'deleted_at']);
        });
    }
};
