<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends laravel-auditing's `audits` table with Recordkeeper-specific columns.
 *
 * laravel-auditing v13 already creates:
 *   - Polymorphic index on (auditable_type, auditable_id)
 *   - Polymorphic index on (user_type, user_id)
 *
 * We add:
 *   - `guard`    — dedicated column (not JSON) so it is indexable and filterable efficiently
 *   - `batch_id` — groups a set of audits from one logical operation
 *   - `context`  — arbitrary JSON metadata (route info, duration, custom data)
 *   - Indexes on `event`, `batch_id`, `created_at`, `guard` for fast filtering
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table): void {
            // Guard column — stored as a proper column, not inside JSON context,
            // so it can be indexed and queried efficiently.
            if (! Schema::hasColumn('audits', 'guard')) {
                $table->string('guard')->nullable()->after('tags');
            }

            // Batch ID — groups related audits from one logical operation.
            if (! Schema::hasColumn('audits', 'batch_id')) {
                $table->string('batch_id')->nullable()->after('guard');
            }

            // Context — arbitrary metadata (request info, custom context, etc.).
            if (! Schema::hasColumn('audits', 'context')) {
                $table->json('context')->nullable()->after('batch_id');
            }
        });

        // Add indexes separately to handle databases that already have them.
        Schema::table('audits', function (Blueprint $table): void {
            // `event` index — filtering by created/updated/deleted/route.* is a core use-case.
            try {
                $table->index('event', 'audits_event_index');
            } catch (Exception) {
            }

            // `guard` index — multi-guard apps filter by guard on every query.
            try {
                $table->index('guard', 'audits_guard_index');
            } catch (Exception) {
            }

            // `batch_id` index — rollback and grouping queries.
            try {
                $table->index('batch_id', 'audits_batch_id_index');
            } catch (Exception) {
            }

            // `created_at` index — time-range filtering and pruning queries.
            try {
                $table->index('created_at', 'audits_created_at_index');
            } catch (Exception) {
            }

            // Composite index for the most common list-page query:
            // "show me all audits for this model, newest first"
            // laravel-auditing's morphs() already creates (auditable_type, auditable_id),
            // but we add a covering index with event for filtered queries.
            try {
                $table->index(['auditable_type', 'auditable_id', 'event'], 'audits_auditable_event_index');
            } catch (Exception) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table): void {
            // Drop indexes first
            foreach (['audits_event_index', 'audits_guard_index', 'audits_batch_id_index', 'audits_created_at_index', 'audits_auditable_event_index'] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (Exception) {
                }
            }

            $cols = array_filter(['guard', 'batch_id', 'context'], fn ($c) => Schema::hasColumn('audits', $c));
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
