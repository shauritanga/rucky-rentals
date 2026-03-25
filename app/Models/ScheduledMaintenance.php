<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledMaintenance extends Model
{
    protected $table = 'scheduled_maintenance';

    protected $fillable = [
        'property_id', 'unit_id', 'title', 'unit_ref', 'category',
        'frequency', 'next_due', 'assignee', 'status', 'notes',
    ];

    protected $casts = ['next_due' => 'date'];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
