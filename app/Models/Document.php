<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'unit_id', 'name', 'type', 'path', 'size',
    ];

    public function unit() { return $this->belongsTo(Unit::class); }
}
