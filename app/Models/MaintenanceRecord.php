<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRecord extends Model
{
    protected $table = 'maintenance_tickets';

    protected $fillable = [
        'property_id',
        'ticket_number',
        'title',
        'description',
        'unit_ref',
        'unit_id',
        'category',
        'priority',
        'status',
        'workflow_status',
        'assignee',
        'reported_by',
        'cost',
        'currency',
        'cost_in_base',
        'reported_date',
        'notes',
    ];

    protected $casts = ['notes' => 'array'];

    protected $appends = ['record_number'];

    public function getRecordNumberAttribute(): string
    {
        return $this->ticket_number ?: ('MR-' . str_pad((string) $this->id, 3, '0', STR_PAD_LEFT));
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
