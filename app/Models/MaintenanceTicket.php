<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceTicket extends Model
{
    protected $fillable = [
        'ticket_number',
        'title',
        'description',
        'unit_ref',
        'unit_id',
        'category',
        'priority',
        'status',
        'assignee',
        'cost',
        'reported_date',
        'notes',
    ];

    protected $casts = ['notes' => 'array'];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
