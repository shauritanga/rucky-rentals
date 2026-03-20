<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Outage extends Model
{
    protected $fillable = ['start_time', 'end_time', 'source', 'reason', 'affected_units', 'notes'];

    protected $casts = ['affected_units' => 'array'];
}
