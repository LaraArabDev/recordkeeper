<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Record of an outbound HTTP request made during a job's execution.
 *
 * @property int $id
 * @property int $audit_id
 * @property string $method
 * @property string $url
 * @property int|null $status_code
 * @property int|null $duration_ms
 * @property bool $failed
 * @property string|null $exception
 * @property array<string, mixed>|null $request_headers
 * @property array<string, mixed>|null $response_headers
 * @property string|null $response_body
 * @property Carbon|null $created_at
 */
class AuditHttpRequest extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'audit_id',
        'method',
        'url',
        'status_code',
        'duration_ms',
        'failed',
        'exception',
        'request_headers',
        'response_headers',
        'response_body',
        'created_at',
    ];

    protected $casts = [
        'failed' => 'boolean',
        'request_headers' => 'array',
        'response_headers' => 'array',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Audit, $this> */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }
}
