<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceTicket extends Model
{
    protected $fillable = [
        'unit_id', 'title', 'description', 'priority', 'status', 'notes',
    ];

    protected $casts = ['notes' => 'array'];

    public function unit() { return $this->belongsTo(Unit::class); }
}
