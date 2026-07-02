<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use LaraArabDev\Recordkeeper\Attributes\Auditable;
use LaraArabDev\Recordkeeper\Attributes\AuditExclude;
use LaraArabDev\Recordkeeper\Attributes\Encrypt;
use LaraArabDev\Recordkeeper\Attributes\Redact;
use LaraArabDev\Recordkeeper\Concerns\AuditsChanges;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Test fixture model with auditing, redaction, encryption, and soft deletes configured via attributes.
 */
#[Auditable(events: ['created', 'updated', 'deleted'], retentionDays: 365)]
#[AuditExclude('internal_notes')]
#[Redact('discount_code')]
#[Encrypt('national_id')]
class Order extends Model implements AuditableContract
{
    use AuditsChanges;
    use SoftDeletes;

    protected $guarded = [];

    protected $table = 'orders';
}
