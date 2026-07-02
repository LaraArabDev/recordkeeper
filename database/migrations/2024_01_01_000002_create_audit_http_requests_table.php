<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_http_requests', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('audit_id')->nullable()->index();
            $table->foreign('audit_id')->references('id')->on('audits')->nullOnDelete();
            $table->string('method', 10);
            $table->text('url');
            $table->integer('status_code')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->boolean('failed')->default(false);
            $table->text('exception')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('response_headers')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_http_requests');
    }
};
