<?php

declare(strict_types=1);

namespace LaraArabDev\Recordkeeper\Models;

use Illuminate\Database\Eloquent\Model;

class AuditTag extends Model
{
    public $timestamps = false;

    protected $table = 'audit_tag';

    /** @var list<string> */
    protected $guarded = [];
}
