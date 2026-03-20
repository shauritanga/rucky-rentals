<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelLog extends Model
{
    protected $fillable = ['date', 'litres', 'cost_per_litre', 'supplier', 'notes'];
}
