<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'resource',
        'property_name',
        'ip_address',
        'result',
        'category',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
