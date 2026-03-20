<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeterReading extends Model
{
    protected $fillable = ['unit_id', 'date', 'prev_reading', 'curr_reading', 'source', 'notes'];

    public function unit() { return $this->belongsTo(Unit::class); }
}
