<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'unit_id', 'name', 'file_path', 'file_type', 'file_size',
        'tag', 'document_type', 'unit_ref', 'tenant_id', 'description', 'uploaded_by',
    ];

    public function unit() { return $this->belongsTo(Unit::class); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
}
