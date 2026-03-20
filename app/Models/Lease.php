<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lease extends Model
{
    protected $fillable = [
        'tenant_id', 'unit_id', 'start_date', 'end_date', 'rent', 'deposit',
        'status', 'approval_log',
    ];

    protected $casts = ['approval_log' => 'array'];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function invoices() { return $this->hasMany(Invoice::class); }
}
