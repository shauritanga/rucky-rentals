<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'unit_id', 'name', 'file_path', 'file_type', 'file_size',
        'tag', 'unit_ref', 'description', 'uploaded_by',
    ];

    public function unit() { return $this->belongsTo(Unit::class); }
}
