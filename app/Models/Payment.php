<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'tenant_id', 'unit_id', 'amount', 'method', 'reference', 'date', 'notes',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
}
