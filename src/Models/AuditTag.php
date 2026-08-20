<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pivot model linking tags to audit records.
 *
 * @property int $id
 * @property int $audit_id
 * @property string $tag
 */
class AuditTag extends Model
{
    public $timestamps = false;

    protected $table = 'audit_tag';

    /** @var list<string> */
    protected $guarded = [];
}
